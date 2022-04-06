<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;

class CollectiveServiceBase {
	/** @var CollectiveMapper */
	protected $collectiveMapper;

	public function __construct(CollectiveMapper $collectiveMapper) {
		$this->collectiveMapper = $collectiveMapper;
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
}
