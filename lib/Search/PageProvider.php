<?php

namespace OCA\Collectives\Search;

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

class PageProvider implements IProvider {
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private CollectiveHelper $collectiveHelper;
	private PageService $pageService;
	private IAppManager $appManager;

	/**
	 * CollectiveProvider constructor.
	 *
	 * @param IL10N            $l10n
	 * @param IURLGenerator    $urlGenerator
	 * @param CollectiveHelper $collectiveHelper
	 * @param PageService      $pageService
	 * @param IAppManager      $appManager
	 */
	public function __construct(IL10N $l10n,
								IURLGenerator $urlGenerator,
								CollectiveHelper $collectiveHelper,
								PageService $pageService,
								IAppManager $appManager) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->pageService = $pageService;
		$this->appManager = $appManager;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'collectives-pages';
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Collectives - Pages');
	}

	/**
	 * @param string $route
	 * @param array  $routeParameters
	 *
	 * @return int
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.Start.index') {
			// Collective pages second when the app is active
			return -2;
		}
		return 4;
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
		if ($this->appManager->isEnabledForUser('circles', $user)) {
			$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false, false);
		} else {
			$collectiveInfos = [];
		}

		$pageSearchResults = [];
		foreach ($collectiveInfos as $collective) {
			$pageInfos = $this->pageService->findByString($collective->getId(), $query->getTerm(), $user->getUID());
			foreach ($pageInfos as $pageInfo) {
				$pageSearchResults[] = new SearchResultEntry(
					'',
					$pageInfo->getTitle(),
					str_replace('{collective}', $collective->getName(), $this->l10n->t('in Collective {collective}')),
					implode('/', array_filter([
						$this->urlGenerator->linkToRoute('collectives.start.index'),
						$this->pageService->getPageLink($collective->getName(), $pageInfo)
					])),
					'icon-collectives-page'
				);
			}
		}

		return SearchResult::complete(
			$this->getName(),
			$pageSearchResults
		);
	}
}
