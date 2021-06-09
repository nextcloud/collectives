<?php


namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Exceptions\CircleDoesNotExistException;
use OCA\Circles\Exceptions\MemberDoesNotExistException;
use OCA\Circles\Model\Circle;
use OCP\AppFramework\QueryException;

class CircleHelper {
	/**
	 * @param string|null $userId
	 *
	 * @return Circle[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
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
	 *
	 * @return Circle
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCircle(string $circleId, ?string $userId = null): Circle {
		try {
			return Circles::detailsCircle($circleId, true);
		} catch (CircleDoesNotExistException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string $name
	 * @param bool   $admin
	 * @param string $userId
	 *
	 * @return Circle|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findCircle(string $name, string $userId, bool $admin = true): ?Circle {
		$circles = Circles::listCircles(
			Circles::CIRCLES_ALL & ~Circles::CIRCLES_PERSONAL,
			$name,
			Circles::LEVEL_ADMIN
		);
		foreach ($circles as $circle) {
			if (strtolower($circle->getName()) === strtolower($name)) {
				return $circle;
			}
		}
		return null;
	}

	/**
	 * @param string      $name
	 * @param string|null $userId
	 *
	 * @return Circle
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function createCircle(string $name, ?string $userId = null): Circle {
		return Circles::createCircle(Circles::CIRCLES_SECRET, $name);
	}

	/**
	 * @param string      $circleId
	 * @param string|null $userId
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function destroyCircle(string $circleId, ?string $userId = null): void {
		try {
			Circles::destroyCircle($circleId);
		} catch (CircleDoesNotExistException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string $circleId
	 * @param bool   $admin
	 * @param string $userId
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function isMember(string $circleId, string $userId, bool $admin = false): bool {
		try {
			$joinedCircles = Circles::joinedCircles($userId);
			foreach ($joinedCircles as $jc) {
				if ($circleId === $jc->getUniqueId()) {
					return true;
				}
			}
		} catch (QueryException $e) {
		}
		return false;
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function isAdmin(string $circleId, string $userId): bool {
		try {
			$member = Circles::getMember(
				$circleId,
				$userId,
				Circles::TYPE_USER);
			// For now only circle owners are admins for the collective
			return ($member !== null && $member->getLevel() >= Circles::LEVEL_OWNER);
		} catch (MemberDoesNotExistException $e) {
			return false;
		}
	}
}
