<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;

class CollectiveServiceBase {
	/** @var CollectiveMapper */
	protected $collectiveMapper;

	/** @var CircleHelper */
	protected $circleHelper;

	public function __construct(CollectiveMapper $collectiveMapper,
								CircleHelper $circleHelper) {
		$this->collectiveMapper = $collectiveMapper;
		$this->circleHelper = $circleHelper;
	}

	/**
	 * @param int    $collectiveId
	 * @param string $userId
	 *
	 * @return Collective
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollective(int $collectiveId, string $userId): Collective {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: '. $collectiveId);
		}

		return $collective;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return CollectiveInfo
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
	 * @param int    $collectiveId
	 * @param string $userId
	 *
	 * @return Collective
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveFromTrash(int $collectiveId, string $userId): Collective {
		if (null === $collective = $this->collectiveMapper->findTrashById($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found in trash: '. $collectiveId);
		}

		return $collective;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return CollectiveInfo
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
