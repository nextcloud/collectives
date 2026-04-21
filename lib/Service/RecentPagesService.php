<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Model\RecentPage;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use RuntimeException;

class RecentPagesService {

	public function __construct(
		protected CollectiveService $collectiveService,
		protected PageService $pageService,
		protected IDBConnection $dbc,
		protected IConfig $config,
		protected IMimeTypeLoader $mimeTypeLoader,
		protected IURLGenerator $urlGenerator,
		protected IL10N $l10n,
		protected IRootFolder $rootFolder,
	) {
	}

	/**
	 * @throws MissingDependencyException
	 * @throws Exception
	 */
	public function forUser(IUser $user, int $limit = 10): array {
		$queryData = $this->getRecentPagesQuery(
			$user->getUID(),
			$limit,
			['p.*', 'f.mtime as timestamp', 'f.name as filename', 'f.path as path']
		);

		if ($queryData === null) {
			return [];
		}

		$r = $queryData['result'];
		$collectivesMap = $queryData['collectivesMap'];

		$pages = [];
		while ($row = $r->fetch()) {
			$collectiveId = (int)explode('/', $row['path'], 4)[2];
			if (!isset($collectivesMap[$collectiveId])) {
				continue;
			}

			// cut out $appDataDir/collectives/%d/ prefix from front, and filename at the rear
			$splitPath = explode('/', $row['path'], 4);
			$internalPath = dirname(array_pop($splitPath));
			unset($splitPath);

			// prepare link and title
			$collectiveUrlPart = $collectivesMap[$collectiveId]->getSlug()
				? $collectivesMap[$collectiveId]->getSlug() . '-' . $collectivesMap[$collectiveId]->getId()
				: $collectivesMap[$collectiveId]->getName();

			$pathParts = [$collectiveUrlPart];
			if ($internalPath !== '' && $internalPath !== '.') {
				$pathParts = array_merge($pathParts, explode('/', $internalPath));
			}
			if ($row['filename'] !== 'Readme.md') {
				$pathParts[] = basename($row['filename'], PageInfo::SUFFIX);
				$title = basename($row['filename'], PageInfo::SUFFIX);
				$pagePath = $internalPath;
			} elseif ($internalPath === '' || $internalPath === '.') {
				$title = $this->l10n->t('Landing page');
				$pagePath = '';
			} else {
				$title = basename($internalPath);
				$pagePath = dirname($internalPath);
			}

			$pagePath = $pagePath === '.'
				? ''
				: str_replace(DIRECTORY_SEPARATOR, ' - ', $pagePath);

			$fileIdSuffix = '?fileId=' . $row['file_id'];
			$url = $row['slug']
				? $this->urlGenerator->linkToRoute('collectives.start.indexPath', ['path' => $collectiveUrlPart . '/' . $row['slug'] . '-' . $row['file_id']])
				: $this->urlGenerator->linkToRoute('collectives.start.indexPath', ['path' => implode('/', $pathParts)]) . $fileIdSuffix;

			// build result model
			// not returning a PageInfo instance because it would be either incomplete or too expensive to build completely
			$recentPage = new RecentPage();
			$recentPage
				->setCollectiveName(CollectiveHelper::getCollectiveNameWithEmoji($collectivesMap[$collectiveId]))
				->setTitle($title)
				->setPagePath($pagePath)
				->setPageUrl($url)
				->setTimestamp($row['timestamp']);
			if ($row['emoji']) {
				$recentPage->setEmoji($row['emoji']);
			}

			$pages[] = $recentPage;
		}
		$r->closeCursor();

		return $pages;
	}

	/**
	 * Get recent pages for user as PageInfo objects (for page picker)
	 *
	 * This is more expensive than forUser() but provides complete PageInfo
	 * structures needed by the page picker component.
	 *
	 * @throws MissingDependencyException
	 * @throws Exception
	 */
	public function forUserAsPageInfo(string $userId, int $limit = 10): array {
		$queryData = $this->getRecentPagesQuery(
			$userId,
			$limit * 2, // Get more results to account for potential errors
			['p.file_id', 'f.path']
		);

		if ($queryData === null) {
			return [];
		}

		$r = $queryData['result'];
		$collectivesMap = $queryData['collectivesMap'];

		$pages = [];
		while ($row = $r->fetch()) {
			if (count($pages) >= $limit) {
				break;
			}

			$collectiveId = (int)explode('/', $row['path'], 4)[2];
			if (!isset($collectivesMap[$collectiveId])) {
				continue;
			}

			try {
				$pageInfo = $this->pageService->find($collectiveId, (int)$row['file_id'], $userId);
				$pageInfo->setCollectiveName($collectivesMap[$collectiveId]->getName());
				$pages[] = $pageInfo;
			} catch (MissingDependencyException|NotFoundException|NotPermittedException) {
				// Skip pages that can't be accessed
				continue;
			}
		}
		$r->closeCursor();

		return $pages;
	}

	/**
	 * Execute query to get recent pages for user by user ID
	 *
	 * @return array{result: \OCP\DB\IResult, collectivesMap: array, appData: string}|null
	 * @throws Exception
	 */
	private function getRecentPagesQuery(string $userId, int $limit, array $selectFields): ?array {
		try {
			$collectives = $this->collectiveService->getCollectives($userId);
		} catch (NotFoundException|NotPermittedException) {
			return null;
		}

		if (!$collectives) {
			return null;
		}

		$qb = $this->dbc->getQueryBuilder();
		$appData = $this->getAppDataFolderName();
		$storageId = $this->rootFolder->get($appData)->getStorage()->getCache()->getNumericStorageId();
		$mimeTypeMd = $this->mimeTypeLoader->getId('text/markdown');

		$expressions = [];
		$collectivesMap = [];
		foreach ($collectives as $collective) {
			$value = sprintf($appData . '/collectives/%d/%%', $collective->getId());
			$expressions[] = $qb->expr()->like('f.path', $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR));
			$collectivesMap[$collective->getId()] = $collective;
		}
		unset($collective);

		$qb->select(...$selectFields)
			->from('collectives_pages', 'p')
			->innerJoin('p', 'filecache', 'f', $qb->expr()->eq('f.fileid', 'p.file_id'))
			->where($qb->expr()->eq('f.storage', $qb->createNamedParameter($storageId, IQueryBuilder::PARAM_STR)))
			->andWhere($qb->expr()->orX(...$expressions))
			->andWhere($qb->expr()->eq('f.mimetype', $qb->createNamedParameter($mimeTypeMd, IQueryBuilder::PARAM_INT)))
			->orderBy('f.mtime', 'DESC')
			->setMaxResults($limit);

		$result = $qb->executeQuery();

		return [
			'result' => $result,
			'collectivesMap' => $collectivesMap,
			'appData' => $appData,
		];
	}

	private function getAppDataFolderName(): string {
		$instanceId = $this->config->getSystemValueString('instanceid', '');
		if ($instanceId === '') {
			throw new RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId;
	}

}
