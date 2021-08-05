<?php


namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\CircleAlreadyExistsException;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\Member;
use OCP\AppFramework\QueryException;

class CircleHelper {
	/**
	 * @param string|null $userId
	 *
	 * @return Circle[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCircles(?string $userId = null): array {
		try {
			return Circles::joinedCircles($userId);
		} catch (QueryException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string      $circleId
	 * @param string|null $userId
	 * @param bool        $super
	 *
	 * @return Circle
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCircle(string $circleId, ?string $userId = null, bool $super = false): Circle {
		try {
			return Circles::detailsCircle($circleId, true);
		} catch (CircleDoesNotExistException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string $name
	 * @param string $userId
	 * @param int    $level
	 *
	 * @return Circle|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findCircle(string $name, string $userId, int $level = Member::LEVEL_MEMBER): ?Circle {
		$circles = Circles::listCircles(
			Circles::CIRCLES_ALL & ~Circles::CIRCLES_PERSONAL,
			$name,
			$level
		);
		foreach ($circles as $circle) {
			if (strtolower($circle->getName()) === strtolower($name)) {
				return $circle;
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 * @param string $userId
	 *
	 * @return Circle
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 * @throws CircleExistsException
	 */
	public function createCircle(string $name, string $userId): Circle {
		try {
			$circle = Circles::createCircle(Circles::CIRCLES_SECRET, $name);
		} catch (CircleAlreadyExistsException $e) {
			throw new CircleExistsException($e->getMessage());
		}

		return $circle;
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function destroyCircle(string $circleId, string $userId): void {
		if (!$this->isOwner($circleId, $userId)) {
			throw new NotPermittedException('Not allowed to destroy circle ' . $circleId);
		}
		try {
			Circles::destroyCircle($circleId);
		} catch (CircleDoesNotExistException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getLevel(string $circleId, string $userId): int {
		try {
			$member = Circles::getMember(
				$circleId,
				$userId,
				Member::TYPE_USER);
			return $member->getLevel();
		} catch (MemberDoesNotExistException $e) {
		}

		// Can still be member of a group that is member.
		try {
			$joinedCircles = Circles::joinedCircles($userId);
			foreach ($joinedCircles as $jc) {
				if ($circleId === $jc->getUniqueId()) {
					// Circles < 22 doesn't provide an easy way to get indirect membership level.
					// So let's just assume simple "member" level.
					return Member::LEVEL_MEMBER;
				}
			}
		} catch (QueryException $e) {
		}

		return Member::LEVEL_NONE;
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 * @param int    $level
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function hasLevel(string $circleId, string $userId, int $level = Member::LEVEL_MEMBER): bool {
		return $this->getLevel($circleId, $userId) >= $level;
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function isAdmin(string $circleId, string $userId): bool {
		return $this->hasLevel($circleId, $userId, Member::LEVEL_ADMIN);
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function isOwner(string $circleId, string $userId): bool {
		return $this->hasLevel($circleId, $userId, Member::LEVEL_OWNER);
	}
}
