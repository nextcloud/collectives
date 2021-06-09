<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IL10N;

class CollectiveService {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/** @var CircleHelper */
	private $circleHelper;

	/** @var IL10N */
	private $l10n;

	/**
	 * CollectiveService constructor.
	 *
	 * @param CollectiveMapper        $collectiveMapper
	 * @param CollectiveHelper        $collectiveHelper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param CircleHelper           $circleHelper
	 * @param IL10N                   $l10n
	 */
	public function __construct(
		CollectiveMapper $collectiveMapper,
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		CircleHelper $circleHelper,
		IL10N $l10n) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->circleHelper = $circleHelper;
		$this->l10n = $l10n;
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveHelper->getCollectivesForUser($userId);
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectivesTrash(string $userId): array {
		return $this->collectiveHelper->getCollectivesTrashForUser($userId);
	}

	/**
	 * @param string      $userId
	 * @param string      $userLang
	 * @param string      $safeName
	 * @param string|null $emoji
	 *
	 * @return array [CollectiveInfo, string]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws UnprocessableEntityException
	 */
	public function createCollective(string $userId, string $userLang, string $safeName, string $emoji = null): array {
		if (empty($safeName)) {
			throw new UnprocessableEntityException('Empty collective name is not allowed');
		}

		// Create a new circle
		$message = '';
		$circle = $this->circleHelper->findCircle($safeName, $userId);
		if (null === $circle) {
			$circle = $this->circleHelper->createCircle($safeName);
		} elseif (!$this->circleHelper->isAdmin($circle->getUniqueId(), $userId)) {
			// We don't have admin access to the circle.
			throw new NotPermittedException('Not allowed to create collective for existing circle.');
		} elseif (null !== $this->collectiveMapper->findByCircleId($circle->getUniqueId(), null, true)) {
			// There's already a collective with that name.
			throw new UnprocessableEntityException('Collective already exists.');
		} else {
			$message = $this->l10n->t(
				'Created collective "%s" for existing circle.',
				[$safeName]
			);
		}

		// Create collective object
		$collective = new Collective();
		$collective->setCircleUniqueId($circle->getUniqueId());
		if ($emoji) {
			$collective->setEmoji($emoji);
		}
		$collective = $this->collectiveMapper->insert($collective);

		// Read in collectiveInfo object
		$collectiveInfo = new CollectiveInfo($collective, $circle->getName(), true);

		// Create folder for collective and optionally copy default landing page
		try {
			$this->collectiveFolderManager->createFolder($collective->getId(), $userLang);
		} catch (InvalidPathException | FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		return [$collectiveInfo, $message];
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function trashCollective(string $userId, int $id): CollectiveInfo {
		if (null === $collective = $this->collectiveMapper->findById($id, $userId)) {
			throw new NotFoundException('Collective not found: ' . $id);
		}
		$name = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());

		if (!$this->circleHelper->isAdmin($collective->getCircleUniqueId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to delete collective: ' . $id);
		}

		return new CollectiveInfo($this->collectiveMapper->trash($collective),
			$name,
			true);
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 * @param bool   $deleteCircle
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function deleteCollective(string $userId, int $id, bool $deleteCircle): CollectiveInfo {
		if (null === $collective = $this->collectiveMapper->findTrashById($id, $userId)) {
			throw new NotFoundException('Collective not found in trash: ' . $id);
		}
		$name = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());

		if ($deleteCircle) {
			$this->circleHelper->destroyCircle($collective->getCircleUniqueId());
		}

		// Delete collective folder and its contents
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder');
		}

		return new CollectiveInfo($this->collectiveMapper->delete($collective), $name, true);
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function restoreCollective(string $userId, int $id): CollectiveInfo {
		if (null === $collective = $this->collectiveMapper->findTrashById($id, $userId)) {
			throw new NotFoundException('Collective not found in trash: ' . $id);
		}
		$name = $this->collectiveMapper->circleUniqueIdToName($collective->getCircleUniqueId());

		return new CollectiveInfo($this->collectiveMapper->restore($collective),
			$name,
			true);
	}
}
