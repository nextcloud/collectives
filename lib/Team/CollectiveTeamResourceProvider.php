<?php

declare(strict_types=1);

namespace OCA\Collectives\Team;

use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Service\CollectiveService;
use OCP\IURLGenerator;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;

class CollectiveTeamResourceProvider implements ITeamResourceProvider {
	public function __construct(private CollectiveMapper $collectiveMapper, private CollectiveService $collectiveService, private IURLGenerator $urlGenerator, private ?string $userId) {

	}

	public function getId(): string {
		return Application::APP_NAME;
	}

	public function getName(): string {
		return 'Collective';
	}

	public function getIconSvg(): string {
		return file_get_contents(__DIR__ . '/../../img/collectives.svg');
	}

	public function getSharedWith(string $teamId): array {
		$collective = $this->collectiveMapper->findByCircleId($teamId);
		if ($collective) {
			$collective = $this->collectiveService->getCollectiveInfo($collective->getId(), $this->userId);
			return [
				new TeamResource(
					$this,
					(string)$collective->getId(),
					$collective->getName(),
					$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . rawurlencode($collective->getName()),
					'',
					'',
					$collective->getEmoji(),
				)
			];
		}
		return [];
	}

	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		// TODO: Implement isSharedWithTeam() method.
	}

	public function getTeamsForResource(string $resourceId): array {
		$collective = $this->collectiveMapper->findByIdAndUser((int)$resourceId, $this->userId);
		return $collective->getCircleId() ? [$collective->getCircleId()] : [];
	}
}
