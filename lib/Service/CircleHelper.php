<?php


namespace OCA\Collectives\Service;

use daita\MySmallPhpTools\Exceptions\InvalidItemException;
use OCA\Circles\CirclesManager;
use OCA\Circles\Exceptions\CircleNotFoundException;
use OCA\Circles\Exceptions\FederatedItemException;
use OCA\Circles\Exceptions\FederatedUserException;
use OCA\Circles\Exceptions\FederatedUserNotFoundException;
use OCA\Circles\Exceptions\InitiatorNotFoundException;
use OCA\Circles\Exceptions\InvalidIdException;
use OCA\Circles\Exceptions\RequestBuilderException;
use OCA\Circles\Exceptions\SingleCircleNotFoundException;
use OCA\Circles\Model\Circle;
use OCA\Circles\Model\FederatedUser;
use OCA\Circles\Model\Member;
use OCP\AppFramework\QueryException;
use Psr\Container\ContainerInterface;

class CircleHelper {
	/** @var CirclesManager | null */
	private $circlesManager;

	/** @var String | null */
	private $dependencyInjectionError;



	public function __construct(ContainerInterface $appContainer) {
		try {
			$this->circlesManager = $appContainer->get(CirclesManager::class);
		} catch (QueryException $e) {
			// Could not instantiate - probably circles app is disabled
			$this->dependencyInjectionError = $e->getMessage();
		}
	}

	/**
	 * @param string|null $userId
	 *
	 * @return FederatedUser|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getFederatedUser(?string $userId = null): ?FederatedUser {
		if (null === $userId) {
			return null;
		}

		try {
			return $this->circlesManager->getFederatedUser($userId, Member::TYPE_USER);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FederatedItemException |
				 RequestBuilderException $e) {
			throw new NotPermittedException($e->getMessage());
		}
	}

	/**
	 * @param string|null $userId
	 *
	 * @throws MissingDependencyException
	 * @throws FederatedUserNotFoundException
	 * @throws SingleCircleNotFoundException
	 * @throws RequestBuilderException
	 * @throws InvalidIdException
	 * @throws FederatedUserException
	 */
	private function startSession(?string $userId = null) {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}
		$federatedUser = $this->getFederatedUser($userId);
		$this->circlesManager->startSession($federatedUser);
	}

	/**
	 * @throws MissingDependencyException
	 */
	private function startSuperSession() {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}
		$this->circlesManager->startSuperSession();
	}

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
			$this->startSession($userId);
			$circles = $this->circlesManager->getCircles();
		} catch (FederatedUserNotFoundException |
				 SingleCircleNotFoundException |
				 RequestBuilderException |
				 InvalidIdException |
				 FederatedUserException |
				 InitiatorNotFoundException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$this->circlesManager->stopSession();

		return $circles;
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
			if ($super) {
				$this->startSuperSession();
			} else {
				$this->startSession($userId);
			}
			$circle = $this->circlesManager->getCircle($circleId);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FederatedUserNotFoundException |
				 SingleCircleNotFoundException |
				 RequestBuilderException |
				 InvalidIdException |
				 FederatedUserException |
				 InitiatorNotFoundException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$this->circlesManager->stopSession();

		return $circle;
	}

	/**
	 * @param string $name
	 * @param bool   $admin
	 * @param string $userId
	 *
	 * @return Circle|null
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function findCircle(string $name, string $userId, bool $admin = true): ?Circle {
		$circles = $this->getCircles($userId);
		foreach ($circles as $circle) {
			if (!strcmp(strtolower($circle->getName()), strtolower($name)) ||
				!strcmp(strtolower($circle->getSanitizedName()), strtolower($name))) {
				if ($admin && !$this->isAdmin($circle->getSingleId(), $userId)) {
					return null;
				}
				return $circle;
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 * @throws NotPermittedException
	 */
	private function existsCircle(string $name): bool {
		$this->circlesManager->startSuperSession();
		try {
			$circles = $this->circlesManager->getCircles();
		} catch (InitiatorNotFoundException | RequestBuilderException $e) {
			throw new NotPermittedException($e->getMessage());
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
	 * @param string      $name
	 * @param string|null $userId
	 *
	 * @return Circle
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws CircleExistsException
	 * @throws MissingDependencyException
	 */
	public function createCircle(string $name, ?string $userId = null): Circle {
		try {
			if ($this->existsCircle($name)) {
				throw new CircleExistsException('A circle with that name exists');
			}
			$this->startSession($userId);
			$circle = $this->circlesManager->createCircle($name, null, false, false);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FederatedUserNotFoundException |
				 SingleCircleNotFoundException |
				 RequestBuilderException |
				 InvalidIdException |
				 FederatedUserException |
				 InitiatorNotFoundException |
				 FederatedItemException |
				 InvalidItemException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$this->circlesManager->stopSession();

		return $circle;
	}

	/**
	 * @param string      $circleId
	 * @param string|null $userId
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function destroyCircle(string $circleId, ?string $userId = null): void {
		try {
			$this->startSession($userId);
			$this->circlesManager->destroyCircle($circleId);
		} catch (CircleNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FederatedUserNotFoundException |
				 SingleCircleNotFoundException |
				 RequestBuilderException |
				 InvalidIdException |
				 FederatedUserException |
				 InitiatorNotFoundException |
				 FederatedItemException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$this->circlesManager->stopSession();
	}

	/**
	 * @param string $circleId
	 * @param bool   $admin
	 * @param string $userId
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function isMember(string $circleId, string $userId, bool $admin = false): bool {
		if (is_null($this->circlesManager)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}
		/** @var FederatedUser $federatedUser */
		$federatedUser = $this->getFederatedUser($userId);

		$level = $admin ? Member::LEVEL_OWNER : Member::LEVEL_MEMBER;
		$memberships = $federatedUser->getMemberships();
		foreach ($memberships as $membership) {
			if ($membership->getCircleId() === $circleId &&
				$membership->getLevel() >= $level) {
				return true;
			}
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
	 * @throws MissingDependencyException
	 */
	public function isAdmin(string $circleId, string $userId): bool {
		return $this->isMember($circleId, $userId, true);
	}
}
