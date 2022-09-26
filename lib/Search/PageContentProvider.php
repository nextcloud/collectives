<?php

namespace OCA\Collectives\Search;

use Exception;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Search\FileSearch\ClauseTokenizer;
use OCA\Collectives\Search\FileSearch\FileSearcher;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Service\SearchService;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use Psr\Log\LoggerInterface;
use TeamTNT\TNTSearch\Support\Highlighter;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\PageService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class PageContentProvider implements IProvider {
	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var PageService */
	private $pageService;

	/** @var IAppManager */
	private $appManager;

	/** @var SearchService */
	private $indexedSearchService;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param IL10N            $l10n
	 * @param IURLGenerator    $urlGenerator
	 * @param CollectiveHelper $collectiveHelper
	 * @param PageService      $pageService
	 * @param SearchService    $indexedSearchService
	 * @param LoggerInterface  $logger
	 * @param IAppManager      $appManager
	 */
	public function __construct(IL10N $l10n,
								IURLGenerator $urlGenerator,
								CollectiveHelper $collectiveHelper,
								PageService $pageService,
								SearchService $indexedSearchService,
								LoggerInterface $logger,
								IAppManager $appManager) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->pageService = $pageService;
		$this->indexedSearchService = $indexedSearchService;
		$this->logger = $logger;
		$this->appManager = $appManager;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'collectives-page-content';
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Collectives - Page content');
	}

	/**
	 * @param string $route
	 * @param array  $routeParameters
	 *
	 * @return int
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.Start.index') {
			return -1;
		}
		return 4;
	}

	/**
	 * @param IUser        $user
	 * @param ISearchQuery $query
	 *
	 * @return SearchResult
	 * @throws FileSearchException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		// Only search for page content if the app is active
		if ($this->appManager->isEnabledForUser('circles', $user) &&
			strpos($query->getRoute(), 'collectives.') === 0) {
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false, false);
		} else {
			$collectiveInfos = [];
		}

		$collectiveMap = [];
		$pages = [];
		foreach ($collectiveInfos as $collective) {
			try {
				$collectiveRoot = $this->pageService->getCollectiveFolder($collective->getId(), $user->getUID());
				$results = $this->indexedSearchService->searchCollective($collective, $query->getTerm());
				foreach ($results as $fileId => $fileData) {
					$file = $collectiveRoot->getById($fileId);
					$pages[$fileId] = reset($file);
					$collectiveMap[$fileId] = $collective;
				}
			} catch (FileSearchException|NotFoundException $e) {
				$this->logger->warning('Collectives file content search failed.', [
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				]);
				continue;
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

			$pageSearchResults[] = new SearchResultEntry(
				'',
				 $highlighter->extractRelevant($query->getTerm(), $page->getContent(), $highlightLength, 5, ''),
				$collective->getName() . ' / ' . $pageInfo->getTitle(),
				implode('/', array_filter([
					$this->urlGenerator->linkToRoute('collectives.start.index'),
					$this->pageService->getPageLink($collective->getName(), $pageInfo)
				])),
				'icon-collectives-page'
			);
		}

		return SearchResult::complete(
			$this->getName(),
			$pageSearchResults
		);
	}

	/**
	 * @param string $term
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

	/**
	 * @param Collective $collective
	 * @param File $file
	 * @param string $userId
	 * @return PageInfo|null
	 */
	private function getPageInfo(Collective $collective, File $file, string $userId): ?PageInfo {
		try {
			return $this->pageService->findByFile($collective->getId(), $file, $userId);
		} catch (Exception $e) {
			return null;
		}
	}
}
