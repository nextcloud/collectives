<?php

namespace OCA\Collectives\Search;

use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
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

	/** @var NodeHelper */
	private $nodeHelper;

	/** @var IAppManager */
	private $appManager;

	/**
	 * CollectiveProvider constructor.
	 *
	 * @param IL10N            $l10n
	 * @param IURLGenerator    $urlGenerator
	 * @param CollectiveHelper $collectiveHelper
	 * @param PageService      $pageService
	 * @param NodeHelper       $nodeHelper
	 * @param IAppManager      $appManager
	 */
	public function __construct(IL10N $l10n,
								IURLGenerator $urlGenerator,
								CollectiveHelper $collectiveHelper,
								PageService $pageService,
								NodeHelper $nodeHelper,
								IAppManager $appManager) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->pageService = $pageService;
		$this->nodeHelper = $nodeHelper;
		$this->appManager = $appManager;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'collectives_pages_content';
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Collectives: page content');
	}

	/**
	 * @param string $route
	 * @param array  $routeParameters
	 *
	 * @return int
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.Start.index') {
			// Collective pages first
			return 0;
		}
		return 5;
	}

	/**
	 * @param IUser        $user
	 * @param ISearchQuery $query
	 *
	 * @return SearchResult
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if ($this->appManager->isEnabledForUser('circles', $user) &&
			strpos($query->getRoute(), 'collectives.') === 0) {
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false);
		} else {
			$collectiveInfos = [];
		}

		$pageSearchResults = [];
		foreach ($collectiveInfos as $collective) {
			$pages = $this->pageService->findAll($user->getUID(), $collective);
			foreach ($pages as $page) {
				$file = $this->nodeHelper->getFileById($this->pageService->getFolder($user->getUID(), $collective, $page->getId()), $page->getId());
				if (preg_match('/(\S+\s+)?(\S+\s*)?' . $query->getTerm() . '(\S*)?(\s+\S+)?/i', NodeHelper::getContent($file), $matches)) {
					$pageSearchResults[] = new SearchResultEntry(
						$this->urlGenerator->imagePath(
							'collectives',
							'app-blue.svg'
						),
						$matches[0],
						str_replace('{page}', $page->getTitle(), str_replace('{collective}', $collective->getName(), $this->l10n->t('in page {page} from collective {collective}'))),
						implode('/', array_filter([
							$this->urlGenerator->linkToRoute('collectives.start.index'),
							$this->pageService->getPageLink($collective->getName(), $page)
						]))
					);
				}
			}
		}

		return SearchResult::complete(
			$this->l10n->t('Collectives: page content'),
			$pageSearchResults
		);
	}
}
