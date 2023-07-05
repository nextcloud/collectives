<?php

declare(strict_types=1);

namespace OCA\Collectives\Search;

use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class CollectiveProvider implements IProvider {
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private CollectiveHelper $collectiveHelper;
	private IAppManager $appManager;
	private CollectiveService $collectiveService;

	/**
	 * CollectiveProvider constructor.
	 *
	 * @param IL10N            $l10n
	 * @param IURLGenerator    $urlGenerator
	 * @param CollectiveHelper $collectiveHelper
	 * @param IAppManager      $appManager
	 */
	public function __construct(IL10N $l10n,
		IURLGenerator $urlGenerator,
		CollectiveHelper $collectiveHelper,
		CollectiveService $collectiveService,
		IAppManager $appManager) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->appManager = $appManager;
		$this->collectiveService = $collectiveService;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'collectives';
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Collectives');
	}

	/**
	 * @param string $route
	 * @param array  $routeParameters
	 *
	 * @return int
	 */
	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.start.index') {
			// Collectives first when the app is active
			return -3;
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

		$collectiveSearchResults = [];
		foreach ($collectiveInfos as $collective) {
			if (stripos($collective->getName(), $query->getTerm()) === false) {
				continue;
			}
			$collectiveSearchResults[] = new SearchResultEntry(
				'',
				$this->collectiveService->getCollectiveNameWithEmoji($collective),
				'',
				$this->urlGenerator->linkToRoute('collectives.start.index') . rawurlencode($collective->getName()),
				'icon-collectives'
			);
		}

		return SearchResult::complete(
			$this->getName(),
			$collectiveSearchResults
		);
	}
}
