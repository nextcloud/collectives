<?php


namespace OCA\Collectives\Fs;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IL10N;

class UserFolderHelper {
	/** @var IRootFolder */
	private $rootFolder;

	/** @var IL10N */
	private $l10n;

	/**
	 * UserFolderHelper constructor.
	 *
	 * @param IRootFolder $rootFolder
	 * @param IL10N       $l10n
	 */
	public function __construct(
		IRootFolder $rootFolder,
		IL10N $l10n) {
		$this->rootFolder = $rootFolder;
		$this->l10n = $l10n;
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 */
	private function initializeUserFolder(string $userId): Folder {
		$userFolder = $this->rootFolder->getUserFolder($userId);
		$userCollectivesPath = $this->l10n->t('Collectives');
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
	 * @return string
	 */
	public function getName(string $userId): string {
		return $this->initializeUserFolder($userId)->getName();
	}

	/**
	 * @param string $userId
	 *
	 * @return Folder
	 */
	public function getFolder(string $userId): Folder {
		return $this->initializeUserFolder($userId);
	}
}
