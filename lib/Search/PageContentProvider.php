<?php

declare(strict_types=1);

namespace OCA\Collectives\Search;

use Exception;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Search\FileSearch\ClauseTokenizer;
use OCA\Collectives\Search\FileSearch\FileSearcher;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\CollectiveHelper;
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
	public function __construct(private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private CollectiveHelper $collectiveHelper,
		private PageService $pageService,
		private SearchService $indexedSearchService,
		private LoggerInterface $logger,
		private IAppManager $appManager) {
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
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false, false);
		} else {
			$collectiveInfos = [];
		}

		$collectiveMap = [];
		$pages = [];
		foreach ($collectiveInfos as $collectiveInfo) {
			try {
				$collectiveRoot = $this->pageService->getCollectiveFolder($collectiveInfo->getId(), $user->getUID());
				$results = $this->indexedSearchService->searchCollective($collectiveInfo, $query->getTerm());
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
					$collectiveMap[$fileId] = $collectiveInfo;
				}
			}
		}

		$highlighter = new Highlighter((new FileSearcher())->getTokenizer());
		$highlightLength = 50;

		$pages = $this->rankPages($query->getTerm(), $pages);
		$pageSearchResults = [];
		foreach ($pages as $page) {
			$collectiveInfo = $collectiveMap[$page->getId()];
			$pageInfo = $this->getPageInfo($collectiveInfo, $page, $user->getUID());
			if (!$pageInfo) {
				continue;
			}

			$pageSearchResults[] = new SearchResultEntry(
				'',
				$highlighter->extractRelevant($query->getTerm(), $page->getContent(), $highlightLength, 5, ''),
				$collectiveInfo->getName() . ' / ' . $pageInfo->getTitle(),
				$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . $this->pageService->getPageLink($collectiveInfo->getName(), $pageInfo),
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

	private function getPageInfo(CollectiveInfo $collectiveInfo, File $file, string $userId): ?PageInfo {
		try {
			return $this->pageService->findByFile($collectiveInfo->getId(), $file, $userId);
		} catch (Exception) {
			return null;
		}
	}
}
