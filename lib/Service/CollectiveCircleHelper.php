<?php

namespace OCA\Unite\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\BaseMember;
use OCA\Unite\Db\CollectiveMapper;
use OCP\AppFramework\QueryException;

class CollectiveCircleHelper {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	public function __construct(CollectiveMapper $collectiveMapper) {
		$this->collectiveMapper = $collectiveMapper;
	}

	public function getCollectivesForUser(string $userId): array {
		$collectives = [];
		$joinedCircles = Circles::joinedCircles($userId);
		foreach ($joinedCircles as $jc) {
			if (null !== $w = $this->collectiveMapper->findByCircleId($jc->getUniqueId())) {
				$collectives[] = $w;
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

		// TODO: directly use `Circles::TYPE_USER` once Circles release after 0.19.4 got released
		try {
			$circleMember = Circles::getMember($collective->getCircleUniqueId(), $userId, BaseMember::TYPE_USER, true);
			if ($userId !== $circleMember->getUserId()) {
				throw new NotFoundException('Collective ' . $collectiveId . ' not found');
			}
		} catch (QueryException | MemberDoesNotExistException $e) {
			throw new NotFoundException('Collective ' . $collectiveId . ' not found');
		}
	}
}
