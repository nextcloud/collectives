<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Fs;

use OC_Util;
use OCA\Collectives\ConfigLexicon;
use OCP\Config\IUserConfig;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IAppConfig;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class UserFolderHelper {
	public function __construct(
		private readonly IRootFolder $rootFolder,
		private readonly IUserManager $userManager,
		private readonly IAppConfig $appConfig,
		private readonly IFactory $l10nFactory,
		private readonly IUserConfig $userConfig,
	) {
	}

	public function getUserFolderSetting(string $userId): string {
		$defaultUserFolder = $this->appConfig->getValueString('collectives', ConfigLexicon::DEFAULT_USER_FOLDER, '');
		// Get collectives user folder from settings and default to translated 'Collectives'
		$userCollectivesPath = $this->userConfig->getValueString($userId, 'collectives', ConfigLexicon::USER_FOLDER, $defaultUserFolder);
		if ($userCollectivesPath === '') {
			$user = $this->userManager->get($userId);
			$userLang = $this->l10nFactory->getUserLanguage($user);
			$l10n = $this->l10nFactory->get('collectives', $userLang);
			$userCollectivesPath = DIRECTORY_SEPARATOR . '.' . $l10n->t('Collectives');
			$this->userConfig->setValueString($userId, 'collectives', ConfigLexicon::USER_FOLDER, $userCollectivesPath);
		}

		return $userCollectivesPath;
	}

	public function get(string $userId): Folder {
		$userHomeFolder = $this->rootFolder->getUserFolder($userId);
		$userCollectivesPath = $this->getUserFolderSetting($userId);
		try {
			/** @var Folder $userCollectivesFolder */
			$userCollectivesFolder = $userHomeFolder->get($userCollectivesPath);
		} catch (NotFoundException) {
			OC_Util::setupFS($userId);
			/** @var Folder $userCollectivesFolder */
			$userCollectivesFolder = $userHomeFolder->get($userCollectivesPath);
		}
		return $userCollectivesFolder;
	}
}
