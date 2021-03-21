<?php


namespace OCA\Collectives\Fs;

use OC\User\NoUserException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\L10N\IFactory;
use OCP\IUserManager;

class UserFolderHelper {
	/** @var IRootFolder */
	private $rootFolder;

	/** @var Folder */
	private $userCollectivesFolder;

	/** @var IUserManager */
	private $userManager;

	/** @var IFactory */
	private $l10nFactory;

	/**
	 * UserFolderHelper constructor.
	 *
	 * @param IRootFolder  $rootFolder
	 * @param IUserManager $userManager
	 * @param IFactory     $l10nFactory
	 */
	public function __construct(
		IRootFolder $rootFolder,
		IUserManager $userManager,
		IFactory $l10nFactory) {
		$this->rootFolder = $rootFolder;
		$this->userManager = $userManager;
		$this->l10nFactory = $l10nFactory;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 */
	private function initialize(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$userLang = $this->l10nFactory->getUserLanguage($this->userManager->get($userId));
		$l10n = $this->l10nFactory->get('collectives', $userLang);
		$userCollectivesPath = $l10n->t('Collectives');
		try {
			$userCollectivesFolder = $userFolder->get($userCollectivesPath);
			// Rename existing node if it's not a folder
			if (!$userCollectivesFolder instanceof Folder) {
				$newFolderName = NodeHelper::generateFilename($userFolder, $userCollectivesPath);
				$userCollectivesFolder->move($userFolder->getPath() . '/' . $newFolderName);
				$userCollectivesFolder = $userFolder->newFolder($userCollectivesPath);
			}
		} catch (NotFoundException $e) {
			$userCollectivesFolder = $userFolder->newFolder($userCollectivesPath);
		}

		return $userCollectivesFolder;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 */
	public function get(string $userId): Folder {
		if (!$this->userCollectivesFolder) {
			$this->userCollectivesFolder = $this->initialize($userId);
		}

		return $this->userCollectivesFolder;
	}

	/**
	 * @param string $collectiveName
	 * @param string $userId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 */
	public function getCollectiveFolder(string $collectiveName, string $userId): Folder {
		try {
			$folder = $this->get($userId)->get($collectiveName);
		} catch (\OCP\Files\NotFoundException | NotPermittedException | NoUserException $e) {
			throw new NotFoundException('Folder not found for collective ' . $collectiveName);
		}

		if (!($folder instanceof Folder)) {
			throw new NotFoundException('Folder not found for collective ' . $collectiveName);
		}
		return $folder;
	}
}
