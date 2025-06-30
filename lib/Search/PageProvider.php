<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private CollectiveHelper $collectiveHelper,
		private CollectiveService $collectiveService,
		private PageService $pageService,
		private IAppManager $appManager,
	) {
	}

	public function getId(): string {
		return 'collectives-pages';
	}

	public function getName(): string {
		return $this->l10n->t('Collectives - Pages');
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.start.index') {
			// Collective pages second when the app is active
			return -2;
		}
		return 4;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if ($this->appManager->isEnabledForUser('circles', $user)) {
			$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false, false);
		} else {
			$collectives = [];
		}

		$pageSearchResults = [];
		foreach ($collectives as $collective) {
			$pageInfos = $this->pageService->findByString($collective->getId(), $query->getTerm(), $user->getUID());
			/** @var PageInfo $pageInfo */
			foreach ($pageInfos as $pageInfo) {
				$descriptionSuffix = $pageInfo->getFilePath()
					? ' - ' . $pageInfo->getFilePathString()
					: '';
				$description = $this->l10n->t('In collective %1$s', [$this->collectiveService->getCollectiveNameWithEmoji($collective)])
					. $descriptionSuffix;
				$pageSearchResults[] = new SearchResultEntry(
					$this->urlGenerator->getAbsoluteURL(
						$this->urlGenerator->imagePath(Application::APP_NAME, 'page.svg')
					),
					$this->getPageTitle($pageInfo),
					$description,
					$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . $this->pageService->getPageLink($collective->getUrlPath(), $pageInfo),
					'icon-collectives-page'
				);
			}
		}

		return SearchResult::complete(
			$this->getName(),
			$pageSearchResults
		);
	}

	private function getPageTitle(PageInfo $pageInfo): string {
		$emoji = $pageInfo->getEmoji();
		return $emoji
			? $emoji . ' ' . $pageInfo->getTitle()
			: $pageInfo->getTitle();
	}
}
