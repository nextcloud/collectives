<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Team;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\IURLGenerator;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;

class CollectiveTeamResourceProvider implements ITeamResourceProvider {
	public function __construct(
		private CollectiveMapper $collectiveMapper,
		private CollectiveService $collectiveService,
		private IURLGenerator $urlGenerator,
		private ?string $userId,
	) {

	}

	public function getId(): string {
		return Application::APP_NAME;
	}

	public function getName(): string {
		return 'Collective';
	}

	/**
	 * @throws NotFoundException
	 */
	public function getIconSvg(): string {
		$icon = file_get_contents(__DIR__ . '/../../img/collectives.svg');
		if ($icon === false) {
			throw new NotFoundException('Failed to read file content of img/collectives.svg');
		}
		return $icon;
	}

	public function getSharedWith(string $teamId): array {
		try {
			$collective = $this->collectiveMapper->findByCircleId($teamId);
			if ($collective) {
				$collective = $this->collectiveService->getCollective($collective->getId(), $this->userId);
			}
		} catch (NotFoundException|NotPermittedException) {
			$collective = null;
		}

		return $collective
			? [new TeamResource(
				$this,
				(string)$collective->getId(),
				$collective->getName(),
				$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . rawurlencode($collective->getName()),
				'',
				'',
				$collective->getEmoji(),
			)]
			: [];
	}

	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		$teamIds = $this->getTeamsForResource($resourceId);
		return in_array($teamId, $teamIds, true);
	}

	public function getTeamsForResource(string $resourceId): array {
		try {
			$collective = $this->collectiveService->getCollective((int)$resourceId, $this->userId);
		} catch (NotFoundException|NotPermittedException) {
			return [];
		}
		return [$collective->getCircleId()];
	}
}
