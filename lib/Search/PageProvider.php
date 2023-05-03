<?php

namespace OCA\Collectives\Search;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
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
	private CollectiveService $collectiveService;

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
		CollectiveService $collectiveService,
		PageService $pageService,
		IAppManager $appManager) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->pageService = $pageService;
		$this->appManager = $appManager;
		$this->collectiveService = $collectiveService;
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
					$this->urlGenerator->getAbsoluteURL(
						$this->urlGenerator->imagePath(Application::APP_NAME, 'page.svg')
					),
					$this->getPageTitle($pageInfo),
					$this->l10n->t('In collective %1$s', [$this->collectiveService->getCollectiveNameWithEmoji($collective)]),
					implode('/', array_filter([
						$this->urlGenerator->linkToRouteAbsolute('collectives.start.index'),
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

	/**
	 * @param PageInfo $pageInfo
	 * @return string
	 */
	private function getPageTitle(PageInfo $pageInfo): string {
		$emoji = $pageInfo->getEmoji();
		return $emoji
			? $emoji . ' ' . $pageInfo->getTitle()
			: $pageInfo->getTitle();
	}
}
