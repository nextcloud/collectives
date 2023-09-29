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
use OCP\IURLGenerator;
use OCP\IUser;

class RecentPagesService {

	public function __construct(
		protected CollectiveService $collectiveService,
		protected IDBConnection $dbc,
		protected IConfig $config,
		protected IMimeTypeLoader $mimeTypeLoader,
		protected IURLGenerator $urlGenerator,
	) {	}

	/**
	 * @return RecentPage[]
	 * @throws MissingDependencyException
	 * @throws Exception
	 */
	public function forUser(IUser $user, int $limit = 10): array {
		try {
			$collectives = $this->collectiveService->getCollectives($user->getUID());
		} catch (NotFoundException|NotPermittedException) {
			return [];
		}

		$qb = $this->dbc->getQueryBuilder();
		$appData = $this->getAppDataFolderName();
		$mimeTypeMd = $this->mimeTypeLoader->getId('text/markdown');

		$expressions = [];
		foreach ($collectives as $collective) {
			$value = sprintf($appData . '/collectives/%d/%%', $collective->getId());
			$expressions[] = $qb->expr()->like('f.path', $qb->createNamedParameter($value, IQueryBuilder::PARAM_STR));
		}

		$qb->select('p.*', 'f.mtime as timestamp', 'f.name as filename', 'f.path as path')
			->from('filecache', 'f')
			->leftJoin('f', 'collectives_pages', 'p', $qb->expr()->eq('f.fileid', 'p.file_id'))
			->where($qb->expr()->orX(...$expressions))
			->andWhere($qb->expr()->eq('f.mimetype', $qb->createNamedParameter($mimeTypeMd, IQueryBuilder::PARAM_INT)))
			->orderBy('f.mtime', 'DESC')
			->setMaxResults($limit);

		$r = $qb->executeQuery();

		$pages = [];
		$collectives = [];
		while ($row = $r->fetch()) {
			$collectiveId = (int)explode('/', $row['path'], 4)[2];
			if (!isset($collectives[$collectiveId])) {
				try {
					// collectives are not cached, but always read from DB, so keep them
					$collectives[$collectiveId] = $this->collectiveService->getCollectiveInfo($collectiveId, $user->getUID());
				} catch (MissingDependencyException|NotFoundException|NotPermittedException) {
					// just skip
					continue;
				}
			}

			// cut out $appDataDir/collectives/%d/ prefix from front, and filename at the rear
			$splitPath = explode('/', $row['path'], 4);
			$internalPath = dirname(array_pop($splitPath));
			unset($splitPath);

			// prepare link and title
			$pathParts = [$collectives[$collectiveId]->getName()];
			if ($internalPath !== '' && $internalPath !== '.') {
				$pathParts = array_merge($pathParts, explode('/', $internalPath));
			}
			if ($row['filename'] !== 'Readme.md') {
				$pathParts[] = basename($row['filename'], PageInfo::SUFFIX);
				$title = basename($row['filename'], PageInfo::SUFFIX);
			} else {
				$title = basename($internalPath);
			}
			$url = $this->urlGenerator->linkToRoute('collectives.start.indexPath', ['path' => implode('/', $pathParts)]);

			// build result model
			// not returning a PageInfo instance because it would be either incomplete or too expensive to build completely
			$recentPage = new RecentPage();
			$recentPage->setCollectiveName($this->collectiveService->getCollectiveNameWithEmoji($collectives[$collectiveId]));
			$recentPage->setTitle($title);
			$recentPage->setPageUrl($url);
			$recentPage->setTimestamp($row['timestamp']);
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
			throw new \RuntimeException('no instance id!');
		}

		return 'appdata_' . $instanceId;
	}

}
