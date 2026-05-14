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
	private ?string $landingPageTranslation = null;

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
				->setCollectiveNameWithEmoji(CollectiveHelper::getCollectiveNameWithEmoji($collectivesMap[$collectiveId]))
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
	public function forUserAsPageInfo(string $userId, ?string $query, int $limit = 10): array {
		$queryData = $this->getRecentPagesQuery(
			$userId,
			$limit * 3, // Get more results to account for title filtering and permission issues
			['p.file_id', 'f.path', 'f.name'],
			$query,
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

			if ($query) {
				// Extract title based on page type
				$filename = $row['name'];

				// Determine title
				$splitPath = explode('/', $row['path'], 4);
				$internalPath = isset($splitPath[3]) ? dirname($splitPath[3]) : '.';

				if ($filename !== PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX) {
					// Regular page - title is filename without extension
					$title = basename($filename, PageInfo::SUFFIX);
				} elseif ($internalPath === '' || $internalPath === '.') {
					// Landing page - set title to "Landing page" (localized) + collective name
					// in order to find searches for "Landing page" as well as searches for collective name
					$title = $this->getLandingPageTranslation()
						. ' '
						. $collectivesMap[$collectiveId]->getName();
				} else {
					// Index page - title is folder name
					$title = basename($internalPath);
				}

				// Check if title matches query (additional filter since DB only checks filename)
				if (stripos($title, $query) === false) {
					continue;
				}
			}

			try {
				$pageInfo = $this->pageService->find($collectiveId, (int)$row['file_id'], $userId);
				$pageInfo->setCollectiveNameWithEmoji(CollectiveHelper::getCollectiveNameWithEmoji($collectivesMap[$collectiveId]));
				$collectiveUrlPart = $collectivesMap[$collectiveId]->getSlug()
					? $collectivesMap[$collectiveId]->getSlug() . '-' . $collectivesMap[$collectiveId]->getId()
					: urlencode($collectivesMap[$collectiveId]->getName());
				$pageInfo->setCollectivePath('/' . $collectiveUrlPart);
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
	private function getRecentPagesQuery(string $userId, int $limit, array $selectFields, ?string $query = null): ?array {
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
			->andWhere($qb->expr()->eq('f.mimetype', $qb->createNamedParameter($mimeTypeMd, IQueryBuilder::PARAM_INT)));
		if ($query) {
			$escapedQuery = $this->dbc->escapeLikeParameter(strtolower($query));
			$nameSearchPattern = '%' . $escapedQuery . '%';
			$pathSearchPattern = $appData . '/collectives/%' . $escapedQuery . '%';

			$searchConditions = [
				// Search in filename
				$qb->expr()->like($qb->func()->lower('f.name'), $qb->createNamedParameter($nameSearchPattern)),
				// Search in path (for index pages)
				$qb->expr()->like($qb->func()->lower('f.path'), $qb->createNamedParameter($pathSearchPattern)),
			];

			$queryMatchesLandingPageTitle = stripos($this->getLandingPageTranslation(), $query) !== false;
			// Searching for landing page, also match root Readme.md files
			foreach ($collectives as $collective) {
				if ($queryMatchesLandingPageTitle || stripos($collective->getName(), $query) !== false) {
					$landingPagePath = sprintf($appData . '/collectives/%d/%s', $collective->getId(), PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
					$searchConditions[] = $qb->expr()->eq('f.path', $qb->createNamedParameter($landingPagePath));
				}
			}

			$qb->andWhere($qb->expr()->orX(...$searchConditions));
		}
		$qb
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

	private function getLandingPageTranslation(): string {
		if (!$this->landingPageTranslation) {
			$this->landingPageTranslation = $this->l10n->t('Landing page');
		}

		return $this->landingPageTranslation;
	}
}
