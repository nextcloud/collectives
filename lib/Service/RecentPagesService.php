<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Model\RecentPage;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IMimeTypeLoader;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use RuntimeException;

class RecentPagesService {

	public function __construct(protected CollectiveService $collectiveService,
		protected IDBConnection $dbc,
		protected IConfig $config,
		protected IMimeTypeLoader $mimeTypeLoader,
		protected IURLGenerator $urlGenerator,
		protected IL10N $l10n) {
	}

	/**
	 * @throws MissingDependencyException
	 * @throws Exception
	 */
	public function forUser(IUser $user, int $limit = 10): array {
		try {
			$collectives = $this->collectiveService->getCollectives($user->getUID());
		} catch (NotFoundException|NotPermittedException) {
			return [];
		}

		if (!$collectives) {
			return [];
		}

		$qb = $this->dbc->getQueryBuilder();
		$appData = $this->getAppDataFolderName();
		$mimeTypeMd = $this->mimeTypeLoader->getId('text/markdown');

		$expressions = [];
		$collectivesMap = [];
		foreach ($collectives as $collective) {
			$value = sprintf($appData . '/collectives/%d/%%', $collective->getId());
			$expressions[] = $qb->expr()->like('f.path', $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR));
			$collectivesMap[$collective->getId()] = $collective;
		}
		unset($collectives);

		$qb->select('p.*', 'f.mtime as timestamp', 'f.name as filename', 'f.path as path')
			->from('filecache', 'f')
			->leftJoin('f', 'collectives_pages', 'p', $qb->expr()->eq('f.fileid', 'p.file_id'))
			->where($qb->expr()->orX(...$expressions))
			->andWhere($qb->expr()->eq('f.mimetype', $qb->createNamedParameter($mimeTypeMd, IQueryBuilder::PARAM_INT)))
			->orderBy('f.mtime', 'DESC')
			->setMaxResults($limit);

		$r = $qb->executeQuery();

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
			$pathParts = [$collectivesMap[$collectiveId]->getName()];
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

			if ($pagePath === '.') {
				$pagePath = '';
			}

			$fileIdSuffix = '?fileId=' . $row['file_id'];
			$url = $this->urlGenerator->linkToRoute('collectives.start.indexPath', ['path' => implode('/', $pathParts)]) . $fileIdSuffix;

			// build result model
			// not returning a PageInfo instance because it would be either incomplete or too expensive to build completely
			$recentPage = new RecentPage();
			$recentPage
				->setCollectiveName($this->collectiveService->getCollectiveNameWithEmoji($collectivesMap[$collectiveId]))
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

	private function getAppDataFolderName(): string {
		$instanceId = $this->config->getSystemValueString('instanceid', '');
		if ($instanceId === '') {
			throw new RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId;
	}

}
