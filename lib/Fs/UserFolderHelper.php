<?php


namespace OCA\Collectives\Fs;

use OC\User\NoUserException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\L10N\IFactory;
use OCP\IUserManager;
use OCP\Lock\LockedException;

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
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function initialize(string $userId): Folder {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (FilesNotPermittedException | NoUserException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$userLang = $this->l10nFactory->getUserLanguage($this->userManager->get($userId));
		$l10n = $this->l10nFactory->get('collectives', $userLang);
		$userCollectivesPath = $l10n->t('Collectives');
		try {
			$userCollectivesFolder = $userFolder->get($userCollectivesPath);
			// Rename existing node if it's not a folder
			if (!$userCollectivesFolder instanceof Folder) {
				$new = NodeHelper::generateFilename($userFolder, $userCollectivesPath);
				$userCollectivesFolder->move($userFolder->getPath() . '/' . $new);
				$userCollectivesFolder = $userFolder->newFolder($userCollectivesPath);
			}
		} catch (FilesNotFoundException $e) {
			try {
				$userCollectivesFolder = $userFolder->newFolder($userCollectivesPath);
			} catch (FilesNotPermittedException $e) {
				throw new NotPermittedException($e->getMessage());
			}
		} catch (InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException | LockedException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		return $userCollectivesFolder;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function get(string $userId): Folder {
		if (!$this->userCollectivesFolder) {
			$this->userCollectivesFolder = $this->initialize($userId);
		}

		return $this->userCollectivesFolder;
	}
}
