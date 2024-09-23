<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Fs;

use OC\User\NoUserException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;
use UnexpectedValueException;

class UserFolderHelper {
	private ?Folder $userCollectivesFolder = null;
	private ?string $initializedUser = null;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IConfig $config,
		private IFactory $l10nFactory,
	) {
	}

	/**
	 * @throws NotPermittedException
	 */
	public function getUserFolderSetting(string $userId): string {
		$defaultUserFolder = $this->config->getAppValue('collectives', 'default_user_folder', '');
		// Get collectives user folder from settings and default to translated 'Collectives'
		$userCollectivesPath = $this->config->getUserValue($userId, 'collectives', 'user_folder', $defaultUserFolder);
		if ($userCollectivesPath === '') {
			$user = $this->userManager->get($userId);

			// Guest users and others with null quota are not allowed to create a subdirectory
			if ($user?->getQuota() === '0 B') {
				return '/';
			}

			$userLang = $this->l10nFactory->getUserLanguage($user);
			$l10n = $this->l10nFactory->get('collectives', $userLang);
			$userCollectivesPath = '/' . $l10n->t('Collectives');
			try {
				$this->config->setUserValue($userId, 'collectives', 'user_folder', $userCollectivesPath);
			} catch (PreConditionNotMetException|UnexpectedValueException $e) {
				throw new NotPermittedException($e->getMessage(), 0, $e);
			}
		}

		return $userCollectivesPath;
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function initialize(string $userId): Folder {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (FilesNotPermittedException|NoUserException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		$userCollectivesPath = $this->getUserFolderSetting($userId);
		// If collectives path is empty (due to null quota), return userFolder
		if ($userCollectivesPath === '/') {
			return $userFolder;
		}

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
				throw new NotPermittedException($e->getMessage(), 0, $e);
			}
		} catch (InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException|LockedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		return $userCollectivesFolder;
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function get(string $userId): Folder {
		if (!$this->userCollectivesFolder || $userId !== $this->initializedUser) {
			$this->userCollectivesFolder = $this->initialize($userId);
			$this->initializedUser = $userId;
		}

		return $this->userCollectivesFolder;
	}
}
