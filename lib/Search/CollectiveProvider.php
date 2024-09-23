<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
	public function __construct(
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private CollectiveHelper $collectiveHelper,
		private CollectiveService $collectiveService,
		private IAppManager $appManager,
	) {
	}

	public function getId(): string {
		return 'collectives';
	}

	public function getName(): string {
		return $this->l10n->t('Collectives');
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.start.index') {
			// Collectives first when the app is active
			return -3;
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

		$collectiveSearchResults = [];
		foreach ($collectives as $collective) {
			if (stripos($collective->getName(), $query->getTerm()) === false) {
				continue;
			}
			$collectiveSearchResults[] = new SearchResultEntry(
				'',
				$this->collectiveService->getCollectiveNameWithEmoji($collective),
				'',
				$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . rawurlencode($collective->getName()),
				'icon-collectives'
			);
		}

		return SearchResult::complete(
			$this->getName(),
			$collectiveSearchResults
		);
	}
}
