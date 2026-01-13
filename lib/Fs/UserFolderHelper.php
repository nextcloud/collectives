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
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\PreConditionNotMetException;
use UnexpectedValueException;

class UserFolderHelper {
	private ?Folder $userCollectivesFolder = null;
	private ?string $initializedUser = null;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IAppConfig $appConfig,
		private IConfig $config,
		private IFactory $l10nFactory,
	) {
	}

	/**
	 * @throws NotPermittedException
	 */
	public function getUserFolderSetting(string $userId): string {
		$defaultUserFolder = $this->appConfig->getValueString('collectives', 'default_user_folder', '');
		// Get collectives user folder from settings and default to translated 'Collectives'
		$userCollectivesPath = $this->config->getUserValue($userId, 'collectives', 'user_folder', $defaultUserFolder);
		if ($userCollectivesPath === '') {
			$user = $this->userManager->get($userId);
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
	 */
	public function getUserRootFolder(string $userId): Folder {
		try {
			return $this->rootFolder->getUserFolder($userId);
		} catch (FilesNotPermittedException|NoUserException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	private function cache(string $userId): Folder {
		$userRootFolder = $this->getUserRootFolder($userId);
		$userCollectivesPath = $this->getUserFolderSetting($userId);

		try {
			$userCollectivesFolder = $userRootFolder->get($userCollectivesPath);
			if (!$userCollectivesFolder instanceof Folder) {
				throw new NotFoundException('Collectives path exists but is not a folder');
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException('Collectives folder mount point not found', 0, $e);
		}

		return $userCollectivesFolder;
	}

	/**
	 * @throws NotPermittedException
	 * @throws NotFoundException
	 */
	public function get(string $userId): Folder {
		if (!$this->userCollectivesFolder || $userId !== $this->initializedUser) {
			$this->userCollectivesFolder = $this->cache($userId);
			$this->initializedUser = $userId;
		}

		return $this->userCollectivesFolder;
	}
}
