<?php

namespace OCA\Collectives\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\AppFramework\QueryException;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class CollectiveService {
	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var NodeHelper */
	private $nodeHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/** @var IUserManager */
	private $userManager;

	/** @var IFactory */
	private $l10nFactory;

	/**
	 * CollectiveService constructor.
	 *
	 * @param CollectiveMapper         $collectiveMapper
	 * @param CollectiveHelper         $collectiveHelper
	 * @param NodeHelper               $nodeHelper
	 * @param CollectiveFolderManager  $collectiveFolderManager
	 * @param IUserManager             $userManager
	 * @param IFactory                 $l10nFactory
	 */
	public function __construct(
		CollectiveMapper $collectiveMapper,
		CollectiveHelper $collectiveHelper,
		NodeHelper $nodeHelper,
		CollectiveFolderManager $collectiveFolderManager,
		IUserManager $userManager,
		IFactory $l10nFactory) {
		$this->collectiveMapper = $collectiveMapper;
		$this->collectiveHelper = $collectiveHelper;
		$this->nodeHelper = $nodeHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->userManager = $userManager;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 * @throws QueryException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveHelper->getCollectivesForUser($userId);
	}

	/**
	 * @param string $userId
	 * @param string $name
	 *
	 * @return Collective
	 * @throws NotPermittedException
	 */
	public function createCollective(string $userId, string $name): Collective {
		if (empty($name)) {
			throw new \RuntimeException('Empty collective name is not allowed');
		}

		$safeName = $this->nodeHelper->sanitiseFilename($name);

		if (null !== $this->collectiveMapper->findByName($safeName)) {
			throw new AlreadyExistsException('Collective already exists: ' . $safeName);
		}

		// Create a new secret circle
		try {
			$circle = Circles::createCircle(2, $safeName);
		} catch (QueryException $e) {
			throw new \RuntimeException('Failed to create Circle ' . $safeName);
		}

		// Create collective object
		$collective = new Collective();
		$collective->setName($name);
		$collective->setCircleUniqueId($circle->getUniqueId());
		$collective = $this->collectiveMapper->insert($collective);

		// Create folder for collective and optionally copy default landing page
		$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
		if (null !== $collectiveFolder &&
			!$collectiveFolder->nodeExists(CollectiveFolderManager::LANDING_PAGE)) {
			$userLang = $this->l10nFactory->getUserLanguage($this->userManager->get($userId));
			$landingPageDir = __DIR__ . '/../../' . CollectiveFolderManager::SKELETON_DIR;
			$landingPagePath = $this->collectiveFolderManager->getLandingPagePath($landingPageDir, $userLang);
			if (false !== $content = file_get_contents($landingPagePath)) {
				$collectiveFolder->newFile(CollectiveFolderManager::LANDING_PAGE . '.' . CollectiveFolderManager::LANDING_PAGE_SUFFIX, $content);
			}
		}

		return $collective;
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return Collective
	 * @throws NotFoundException
	 */
	public function deleteCollective(string $userId, int $id): Collective {
		if (null === $collective = $this->collectiveMapper->findById($id, $userId)) {
			throw new NotFoundException('Collective not found: '. $id);
		}

		try {
			Circles::destroyCircle($collective->getCircleUniqueId());
		} catch (QueryException $e) {
			throw new NotFoundException('Circle not found: ' . $collective->getCircleUniqueId());
		}

		try {
			if (null !== $collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId(), false)) {
				$collectiveFolder->delete();
			}
		} catch (InvalidPathException | \OCP\Files\NotFoundException | NotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder');
		}

		return $this->collectiveMapper->delete($collective);
	}
}
