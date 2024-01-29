<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;

class CollectiveServiceBase {
	public function __construct(protected CollectiveMapper $collectiveMapper, protected CircleHelper $circleHelper) {
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollective(int $collectiveId, string $userId): Collective {
		if (null === $collective = $this->collectiveMapper->findByIdAndUser($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}

		return $collective;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveInfo(int $id, string $userId): CollectiveInfo {
		$collective = $this->getCollective($id, $userId);
		$name = $this->collectiveMapper->circleIdToName($collective->getCircleId(), $userId);
		$level = $this->circleHelper->getLevel($collective->getCircleId(), $userId);

		return new CollectiveInfo($collective, $name, $level);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveFromTrash(int $collectiveId, string $userId): Collective {
		if (null === $collective = $this->collectiveMapper->findTrashByIdAndUser($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found in trash: '. $collectiveId);
		}

		return $collective;
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveInfoFromTrash(int $id, string $userId): CollectiveInfo {
		$collective = $this->getCollectiveFromTrash($id, $userId);
		$name = $this->collectiveMapper->circleIdToName($collective->getCircleId(), $userId);
		$level = $this->circleHelper->getLevel($collective->getCircleId(), $userId);

		return new CollectiveInfo($collective, $name, $level);
	}
}
