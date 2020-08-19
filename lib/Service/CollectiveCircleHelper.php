<?php

namespace OCA\Unite\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Unite\Db\Collective;
use OCA\Unite\Db\CollectiveMapper;
use OCP\AppFramework\QueryException;

class CollectiveCircleHelper {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/**
	 * CollectiveCircleHelper constructor.
	 *
	 * @param CollectiveMapper $collectiveMapper
	 */
	public function __construct(CollectiveMapper $collectiveMapper) {
		$this->collectiveMapper = $collectiveMapper;
	}

	/**
	 * @param string $userId
	 *
	 * @return Collective[]
	 * @throws QueryException
	 */
	public function getCollectivesForUser(string $userId): array {
		$collectives = [];
		$joinedCircles = Circles::joinedCircles($userId);
		foreach ($joinedCircles as $jc) {
			if (null !== $c = $this->collectiveMapper->findByCircleId($jc->getUniqueId())) {
				$collectives[] = $c;
			}
		}
		return $collectives;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 *
	 * @throws NotFoundException
	 */
	public function userHasCollective(string $userId, int $collectiveId): void {
		if (null === $collective = $this->collectiveMapper->findById($collectiveId)) {
			throw new NotFoundException('Collective ' . $collectiveId . ' not found');
		}

		try {
			$circleMember = Circles::getMember($collective->getCircleUniqueId(), $userId, Circles::TYPE_USER, true);
			if ($userId !== $circleMember->getUserId()) {
				throw new NotFoundException('Collective ' . $collectiveId . ' not found');
			}
		} catch (QueryException | MemberDoesNotExistException $e) {
			throw new NotFoundException('Collective ' . $collectiveId . ' not found');
		}
	}
}
