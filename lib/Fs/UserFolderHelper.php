<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Fs;

use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class UserFolderHelper {
	public function __construct(
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private IAppConfig $appConfig,
		private IConfig $config,
		private IFactory $l10nFactory,
	) {
	}

	public function getUserFolderSetting(string $userId): string {
		$defaultUserFolder = $this->appConfig->getValueString('collectives', 'default_user_folder', '');
		// Get collectives user folder from settings and default to translated 'Collectives'
		$userCollectivesPath = $this->config->getUserValue($userId, 'collectives', 'user_folder', $defaultUserFolder);
		if ($userCollectivesPath === '') {
			$user = $this->userManager->get($userId);
			$userLang = $this->l10nFactory->getUserLanguage($user);
			$l10n = $this->l10nFactory->get('collectives', $userLang);
			$userCollectivesPath = DIRECTORY_SEPARATOR . '.' . $l10n->t('Collectives');
			$this->config->setUserValue($userId, 'collectives', 'user_folder', $userCollectivesPath);
		}

		return $userCollectivesPath;
	}

	public function get(string $userId): Folder {
		$userHomeFolder = $this->rootFolder->getUserFolder($userId);
		$userCollectivesPath = $this->getUserFolderSetting($userId);
		try {
			/** @var Folder $userCollectivesFolder */
			$userCollectivesFolder = $userHomeFolder->get($userCollectivesPath);
		} catch (NotFoundException $e) {
			\OC_Util::setupFS($userId);
			/** @var Folder $userCollectivesFolder */
			$userCollectivesFolder = $userHomeFolder->get($userCollectivesPath);
		}
		return $userCollectivesFolder;
	}
}
