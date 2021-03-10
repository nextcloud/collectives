<?php

namespace OCA\Collectives\Search;

use OCA\Collectives\Service\CollectiveHelper;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class CollectiveProvider implements IProvider {
	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var IAppManager */
	private $appManager;

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
								IAppManager $appManager) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->appManager = $appManager;
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
		if ($route === 'collectives.Start.index') {
			// Collectives first
			return 0;
		}
		return 4;
	}

	/**
	 * @param IUser        $user
	 * @param ISearchQuery $query
	 *
	 * @return SearchResult
	 * @throws QueryException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if ($this->appManager->isEnabledForUser('circles', $user)) {
			$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID());
		} else {
			$collectives = [];
		}

		$collectiveSearchResults = [];
		foreach ($collectives as $collective) {
			if (stripos($collective->getName(), $query->getTerm()) === false) {
				continue;
			}
			$collectiveSearchResults[] = new SearchResultEntry(
				$this->urlGenerator->imagePath(
					'collectives',
					'ant.svg'
				),
				$collective->getName(),
				'',
				$this->urlGenerator->linkToRoute('collectives.start.index') . '/' . rawurlencode($collective->getName())
			);
		}

		return SearchResult::complete(
			$this->l10n->t('Collectives'),
			$collectiveSearchResults
		);
	}
}
