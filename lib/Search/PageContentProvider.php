<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search;

use Exception;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Search\FileSearch\ClauseTokenizer;
use OCA\Collectives\Search\FileSearch\FileSearcher;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Psr\Log\LoggerInterface;
use TeamTNT\TNTSearch\Support\Highlighter;

class PageContentProvider implements IProvider {
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private CollectiveHelper $collectiveHelper,
		private CollectiveService $collectiveService,
		private PageService $pageService,
		private SearchService $indexedSearchService,
		private LoggerInterface $logger,
		private IAppManager $appManager,
	) {
	}

	public function getId(): string {
		return 'collectives-page-content';
	}

	public function getName(): string {
		return $this->l10n->t('Collectives - Page content');
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.start.index') {
			return -1;
		}
		return 4;
	}

	/**
	 * @throws FileSearchException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if ($this->appManager->isEnabledForUser('circles', $user)) {
			$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false, false);
		} else {
			$collectives = [];
		}

		$collectiveMap = [];
		$pages = [];
		foreach ($collectives as $collective) {
			try {
				$collectiveRoot = $this->pageService->getCollectiveFolder($collective->getId(), $user->getUID());
				$results = $this->indexedSearchService->searchCollective($collective, $query->getTerm());
			} catch (FileSearchException|NotFoundException $e) {
				$this->logger->warning('Collectives file content search failed.', [
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				]);
				continue;
			}
			foreach ($results as $fileId => $fileData) {
				$fileEntries = $collectiveRoot->getById($fileId);
				if (!empty($fileEntries)) {
					$pages[$fileId] = $fileEntries[0];
					$collectiveMap[$fileId] = $collective;
				}
			}
		}

		$highlighter = new Highlighter((new FileSearcher())->getTokenizer());
		$highlightLength = 50;

		$pages = $this->rankPages($query->getTerm(), $pages);
		$pageSearchResults = [];
		foreach ($pages as $page) {
			$collective = $collectiveMap[$page->getId()];
			$pageInfo = $this->getPageInfo($collective, $page, $user->getUID());
			if (!$pageInfo) {
				continue;
			}

			$descriptionSuffix = $pageInfo->getFilePath()
				? ' - ' . $pageInfo->getFilePathString()
				: '';
			$description = $this->l10n->t('In collective %1$s', [$this->collectiveService->getCollectiveNameWithEmoji($collective)])
				. $descriptionSuffix;
			$pageSearchResults[] = new SearchResultEntry(
				'',
				$highlighter->extractRelevant($query->getTerm(), $page->getContent(), $highlightLength, 5, ''),
				$description,
				$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . $this->pageService->getPageLink($collective->getUrlPath(), $pageInfo),
				'icon-collectives-page'
			);
		}

		return SearchResult::complete(
			$this->getName(),
			$pageSearchResults
		);
	}

	/**
	 * @param File[] $pages
	 * @return File[]
	 * @throws FileSearchException
	 */
	private function rankPages(string $term, array $pages): array {
		if (!$this->indexedSearchService->areDependenciesMet()) {
			return $pages;
		}

		$ranked = [];
		$searcher = new FileSearcher();

		// Run once using clause tokenizer to extract most relevant results (if term has at least two words)
		$words = explode(' ', $term);
		if (count($words) > 1) {
			$config = FileSearcher::DEFAULT_CONFIG;
			$config['tokenizer'] = ClauseTokenizer::class;
			$searcher->loadConfig($config);

			$searcher->createInMemoryIndex()->run($pages);
			$results = $searcher->search($term);
			foreach (array_keys($results) as $pageId) {
				$ranked[] = $pages[$pageId];
				unset($pages[$pageId]);
			}
		}

		// Run using default tokenizer to rank remaining results
		$searcher = new FileSearcher();
		$searcher->createInMemoryIndex()->run($pages);
		$results = $searcher->search($term, 50);
		foreach (array_keys($results) as $pageId) {
			$ranked[] = $pages[$pageId];
		}

		return $ranked;
	}

	private function getPageInfo(Collective $collective, File $file, string $userId): ?PageInfo {
		try {
			return $this->pageService->findByFile($collective->getId(), $file, $userId);
		} catch (Exception) {
			return null;
		}
	}
}
