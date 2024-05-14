<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCA\Circles\Model\Probes\CircleProbe;
use OCA\Circles\Tools\Exceptions\InvalidItemException;
use OCP\AppFramework\QueryException;
use OCP\AutoloadNotAllowedException;
use Psr\Container\ContainerInterface;

class CircleHelper {
	private ?CirclesManager $circlesManager = null;
	private string $dependencyInjectionError = '';

	public function __construct(ContainerInterface $appContainer) {
		try {
			$this->circlesManager = $appContainer->get(CirclesManager::class);
		} catch (QueryException|AutoloadNotAllowedException $e) {
			// Could not instantiate - probably teams app is disabled
			$this->dependencyInjectionError = $e->getMessage();
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getFederatedUser(?string $userId = null): ?FederatedUser {
		if ($userId === null) {
			return null;
		}

		try {
			return $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FederatedItemException |
				 RequestBuilderException $e) {
				 	throw new NotPermittedException($e->getMessage(), 0, $e);
				 }
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws RequestBuilderException
	 * @throws FederatedItemException
	 */
	private function startSession(?string $userId = null): void {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}
		$federatedUser = $this->getFederatedUser($userId);
		$this->circlesManager->startSession($federatedUser);
	}

	/**
	 * @throws MissingDependencyException
	 */
	private function startSuperSession(): void {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}
		$this->circlesManager->startSuperSession();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCircles(?string $userId = null): array {
		try {
			$this->startSession($userId);
			$probe = new CircleProbe();
			$probe->mustBeMember();
			$circles = $this->circlesManager->getCircles($probe, true);
		} catch (RequestBuilderException |
				 FederatedItemException $e) {
				 	throw new NotPermittedException($e->getMessage(), 0, $e);
				 }
		$this->circlesManager->stopSession();

		return $circles;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCircle(string $circleId, ?string $userId = null, bool $super = false): Circle {
		try {
			if ($super) {
				$this->startSuperSession();
			} else {
				$this->startSession($userId);
			}
			$circle = $this->circlesManager->getCircle($circleId);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (RequestBuilderException |
				 FederatedItemException $e) {
				 	throw new NotPermittedException($e->getMessage(), 0, $e);
				 }
		$this->circlesManager->stopSession();

		return $circle;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findCircle(string $name, string $userId, int $level = Member::LEVEL_MEMBER): ?Circle {
		$circles = $this->getCircles($userId);
		foreach ($circles as $circle) {
			if (!strcmp(strtolower($circle->getName()), strtolower($name)) ||
				!strcmp(strtolower($circle->getSanitizedName()), strtolower($name))) {
				if (!$this->hasLevel($circle->getSingleId(), $userId, $level)) {
					return null;
				}
				return $circle;
			}
		}
		return null;
	}

	/**
	 * @throws NotPermittedException
	 */
	private function existsCircle(string $name): bool {
		$this->circlesManager->startSuperSession();
		try {
			$circles = $this->circlesManager->getCircles();
		} catch (InitiatorNotFoundException | RequestBuilderException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
		$this->circlesManager->stopSession();

		foreach ($circles as $circle) {
			if (!strcmp(strtolower($circle->getName()), strtolower($name)) ||
				!strcmp(strtolower($circle->getSanitizedName()), strtolower($name))) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws CircleExistsException
	 * @throws MissingDependencyException
	 */
	public function createCircle(string $name, string $userId): Circle {
		try {
			if ($this->existsCircle($name)) {
				throw new CircleExistsException('A team with that name exists');
			}
			$this->startSession($userId);
			$circle = $this->circlesManager->createCircle($name, null, false, false);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (RequestBuilderException |
				 FederatedItemException |
				 InvalidItemException $e) {
				 	throw new NotPermittedException($e->getMessage(), 0, $e);
				 }
		$this->circlesManager->stopSession();
		$this->flagCircleAsAppManaged($circle->getSingleId());

		return $circle;
	}

	/**
	 * @throws NotPermittedException
	 */
	public function flagCircleAsAppManaged(string $circleId): void {
		$this->circlesManager->startSuperSession();
		try {
			$this->circlesManager->flagAsAppManaged($circleId);
		} catch (RequestBuilderException |
		FederatedItemException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
		$this->circlesManager->stopSession();
	}

	/**
	 * @throws NotPermittedException
	 */
	public function unflagCircleAsAppManaged(string $circleId): void {
		$this->circlesManager->startSuperSession();
		try {
			$this->circlesManager->flagAsAppManaged($circleId, false);
		} catch (RequestBuilderException |
				 FederatedItemException $e) {
				 	throw new NotPermittedException($e->getMessage(), 0, $e);
				 }
		$this->circlesManager->stopSession();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function destroyCircle(string $circleId, string $userId): void {
		if (!$this->isOwner($circleId, $userId)) {
			throw new NotPermittedException('Not allowed to destroy team ' . $circleId);
		}
		try {
			$this->unflagCircleAsAppManaged($circleId);
			$this->startSession($userId);
			$this->circlesManager->destroyCircle($circleId);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (RequestBuilderException |
			FederatedItemException $e) {
				throw new NotPermittedException($e->getMessage(), 0, $e);
			}
		$this->circlesManager->stopSession();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getLevel(string $circleId, string $userId): int {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}

		try {
			$this->startSession($userId);
			$circle = $this->circlesManager->getCircle($circleId);
			$member = $circle->getInitiator();
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (RequestBuilderException |
			FederatedItemException $e) {
				throw new NotPermittedException($e->getMessage(), 0, $e);
			}

		return $member->getLevel();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function canLeave(string $circleId, string $userId): bool {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}

		try {
			$this->startSession($userId);
			$circle = $this->circlesManager->getCircle($circleId);
			$initiator = $circle->getInitiator();
			if ($initiator->getUserType() !== Member::TYPE_USER) {
				return false;
			}
			$members = $circle->getMembers();
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		foreach ($members as $member) {
			if ($member->getSingleId() !== $initiator->getSingleId()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function hasLevel(string $circleId, string $userId, int $level = Member::LEVEL_MEMBER): bool {
		return $this->getLevel($circleId, $userId) >= $level;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function isAdmin(string $circleId, string $userId): bool {
		return $this->hasLevel($circleId, $userId, Member::LEVEL_ADMIN);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function isOwner(string $circleId, string $userId): bool {
		return $this->hasLevel($circleId, $userId, Member::LEVEL_OWNER);
	}
}
