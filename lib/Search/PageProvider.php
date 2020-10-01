<?php

namespace OCA\Collectives\Search;

use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\PageService;
use OCP\AppFramework\QueryException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class PageProvider implements IProvider {
	/** @var IL10N */
	private $l10n;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var PageService */
	private $pageService;

	/**
	 * CollectiveProvider constructor.
	 *
	 * @param IL10N            $l10n
	 * @param IURLGenerator    $urlGenerator
	 * @param CollectiveHelper $collectiveHelper
	 * @param PageService      $pageService
	 */
	public function __construct(IL10N $l10n,
								IURLGenerator $urlGenerator,
								CollectiveHelper $collectiveHelper,
								PageService $pageService) {
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->collectiveHelper = $collectiveHelper;
		$this->pageService = $pageService;
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'collectives_pages';
	}

	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->l10n->t('Collective Pages');
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
		return 5;
	}

	/**
	 * @param IUser        $user
	 * @param ISearchQuery $query
	 *
	 * @return SearchResult
	 * @throws QueryException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID());
		$pageSearchResults = [];
		foreach ($collectives as $collective) {
			$pages = $this->pageService->findByString($user->getUID(), $collective->getId(), $query->getTerm());
			foreach ($pages as $page) {
				$pageSearchResults[] = new SearchResultEntry(
					$this->urlGenerator->imagePath(
						'collectives',
						'ant.svg'
					),
					$page->getTitle(),
					str_replace('{collective}', $collective->getName(), $this->l10n->t('in {collective}')),
					$this->urlGenerator->linkToRoute('collectives.start.index') . '/' . rawurlencode($collective->getName()) . '/' . rawurlencode($page->getTitle())
				);
			}
		}

		return SearchResult::complete(
			$this->l10n->t('Collective Pages'),
			$pageSearchResults
		);
	}
}
