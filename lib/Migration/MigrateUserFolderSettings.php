<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Migration;

use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class MigrateUserFolderSettings implements IRepairStep {
	public function __construct(
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly IUserManager $userManager,
		private readonly IFactory $l10nFactory,
	) {
	}

	public function getName():string {
		return 'Migrate user folder settings to new default name';
	}

	public function run(IOutput $output): void {
		if ($this->appConfig->getValueBool('collectives', 'migrated_user_folder_settings')) {
			$output->info('User folder settings already migrated');
			return;
		}

		$output->info('Migrating user folder settings ...');
		$output->startProgress();

		$this->userManager->callForSeenUsers(function (IUser $user) use ($output) {
			$oldDefaultUserFolderPath = DIRECTORY_SEPARATOR . 'Collectives';
			$newDefaultUserFolderPath = DIRECTORY_SEPARATOR . '.' . 'Collectives';
			$userFolderPath = $this->config->getUserValue($user->getUID(), 'collectives', 'user_folder', '');
			if ($userFolderPath === '') {
				// No user folder path configured, doesn't use Collectives
				return;
			}

			if ($userFolderPath === $newDefaultUserFolderPath) {
				// New default English user folder path, already migrated
				return;
			}

			if ($userFolderPath === $oldDefaultUserFolderPath) {
				// Old default English user folder path, update setting
				$this->config->setUserValue($user->getUID(), 'collectives', 'user_folder', $newDefaultUserFolderPath);
				$output->advance();
				return;
			}

			$userLang = $this->l10nFactory->getUserLanguage($user);
			$l10n = $this->l10nFactory->get('collectives', $userLang);
			$oldDefaultUserFolderPathL10n = DIRECTORY_SEPARATOR . $l10n->t('Collectives');
			$newDefaultUserFolderPathL10n = DIRECTORY_SEPARATOR . '.' . $l10n->t('Collectives');

			if ($userFolderPath === $oldDefaultUserFolderPathL10n) {
				// Old default localized user folder path, update setting
				$this->config->setUserValue($user->getUID(), 'collectives', 'user_folder', $newDefaultUserFolderPathL10n);
				$output->advance();
			}
		});

		$output->finishProgress();
		$output->info('done');

		$this->appConfig->setValueBool('collectives', 'migrated_user_folder_settings', true);
	}
}
