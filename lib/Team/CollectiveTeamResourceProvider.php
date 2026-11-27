<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Team;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException as CollectiveNotPermittedException;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCA\Guests\GuestManager;
use OCP\Files\NotPermittedException;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Server;
use OCP\Teams\ITeamOwnedResourceProvider;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamFolder;
use OCP\Teams\TeamResource;

class CollectiveTeamResourceProvider implements ITeamResourceProvider, ITeamOwnedResourceProvider {
	public function __construct(
		private readonly CollectiveMapper $collectiveMapper,
		private readonly CollectiveService $collectiveService,
		private readonly IURLGenerator $urlGenerator,
		private readonly ?string $userId,
		private readonly IUserManager $userManager,
		private readonly IFactory $l10nFactory,
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
		$icon = file_get_contents(__DIR__ . '/../../img/collectives-dark.svg');
		if ($icon === false) {
			throw new NotFoundException('Failed to read file content of img/collectives-dark.svg');
		}
		return $icon;
	}

	public function getSharedWith(string $teamId): array {
		try {
			$collective = $this->collectiveMapper->findByCircleId($teamId);
			if ($collective) {
				$collective = $this->collectiveService->getCollective($collective->getId(), $this->userId);
			}
		} catch (NotFoundException|CollectiveNotPermittedException) {
			$collective = null;
		}

		return $collective ? [$this->toTeamResource($collective)] : [];
	}

	/** @return list<TeamResource> */
	public function getOwnedByTeam(string $teamId, string $userId, bool $isAdmin, ?TeamFolder $folder): array {
		$collective = $this->collectiveMapper->findByCircleId($teamId);
		if ($collective === null) {
			return [];
		}
		$collective = $this->collectiveService->getCollective($collective->getId(), $userId);
		return [$this->toTeamResource($collective)];
	}

	public function toTeamResource(Collective $collective): TeamResource {
		return new TeamResource(
			$this,
			(string)$collective->getId(),
			$collective->getName(),
			$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . rawurlencode($collective->getName()),
			'',
			'',
			$collective->getEmoji(),
		);
	}

	public function canCreateResource(string $teamId, string $userId, bool $isAdmin, ?TeamFolder $folder): bool {
		return $isAdmin
			&& !(class_exists(GuestManager::class) && Server::get(GuestManager::class)->isGuest($userId))
			&& $this->collectiveMapper->findByCircleId($teamId, true) === null;
	}

	public function createResource(string $teamId, string $teamName, string $userId, bool $isAdmin, ?TeamFolder $folder): string {
		if (!$this->canCreateResource($teamId, $userId, $isAdmin, $folder)) {
			throw new NotPermittedException('Cannot add a collective to this team');
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			throw new NotPermittedException('User not found');
		}
		try {
			$collective = $this->collectiveService->createCollectiveForTeam(
				$teamId,
				$userId,
				$this->l10nFactory->getUserLanguage($user),
			);
		} catch (CollectiveNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		} catch (UnprocessableEntityException $e) {
			throw new \InvalidArgumentException($e->getMessage(), 0, $e);
		}
		return (string)$collective->getId();
	}

	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		$teamIds = $this->getTeamsForResource($resourceId);
		return in_array($teamId, $teamIds, true);
	}

	public function getTeamsForResource(string $resourceId): array {
		try {
			$collective = $this->collectiveService->getCollective((int)$resourceId, $this->userId);
		} catch (NotFoundException|CollectiveNotPermittedException) {
			return [];
		}
		return [$collective->getCircleId()];
	}
}
