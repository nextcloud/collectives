<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OC\Files\Filesystem;
use OC\Files\View;
use OCP\IAppConfig;
use OCP\Files\FileInfo;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;

class UserFolderEncryptionKeyRepairService {
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly IConfig $config,
		private readonly IUserManager $userManager,
		private readonly IFactory $l10nFactory,
		private readonly View $rootView,
	) {
	}

	public function repair(bool $dryRun = false, ?string $userId = null, ?callable $report = null): UserFolderEncryptionKeyRepairResult {
		$result = new UserFolderEncryptionKeyRepairResult($dryRun);
		$targetUserFolderPaths = $this->getTargetUserFolderPaths($userId);
		$sourceUserFolderPaths = $this->getSourceUserFolderPaths($targetUserFolderPaths);
		$availableSourcePaths = [];

		foreach ($sourceUserFolderPaths as $sourceUserFolderPath) {
			$sourcePath = $this->getKeyTreePath($sourceUserFolderPath);
			if (!$this->rootView->file_exists($sourcePath) || !$this->rootView->is_dir($sourcePath)) {
				continue;
			}

			$availableSourcePaths[$sourceUserFolderPath] = $sourcePath;
			$result->addSourceTree();
		}

		foreach ($targetUserFolderPaths as $targetUserFolderPath) {
			$result->addTargetPath();
			$targetPath = $this->getKeyTreePath($targetUserFolderPath);

			foreach ($availableSourcePaths as $sourceUserFolderPath => $sourcePath) {
				if ($sourceUserFolderPath === $targetUserFolderPath) {
					continue;
				}

				$this->mergeMissingTree(
					$sourcePath,
					$targetPath,
					$result,
					$report,
				);
			}
		}

		return $result;
	}

	/**
	 * @return list<string>
	 */
	private function getTargetUserFolderPaths(?string $userId): array {
		$userFolderPaths = [];
		$defaultUserFolderPath = $this->appConfig->getValueString('collectives', 'default_user_folder', '');

		$addPath = function (IUser $user) use (&$userFolderPaths, $defaultUserFolderPath): void {
			$userFolderPath = $this->getEffectiveUserFolderPath($user, $defaultUserFolderPath);
			if ($userFolderPath === null) {
				return;
			}

			$userFolderPaths[$userFolderPath] = $userFolderPath;
		};

		if ($userId !== null) {
			$user = $this->userManager->get($userId);
			if ($user !== null) {
				$addPath($user);
			}
			return array_values($userFolderPaths);
		}

		$this->userManager->callForSeenUsers($addPath);
		return array_values($userFolderPaths);
	}

	/**
	 * @param list<string> $targetUserFolderPaths
	 * @return list<string>
	 */
	private function getSourceUserFolderPaths(array $targetUserFolderPaths): array {
		$sourceUserFolderPaths = [];

		foreach ($targetUserFolderPaths as $userFolderPath) {
			$sourceUserFolderPaths[$userFolderPath] = $userFolderPath;
			$aliasedPath = $this->toggleHiddenMountSegment($userFolderPath);
			if ($aliasedPath !== null) {
				$sourceUserFolderPaths[$aliasedPath] = $aliasedPath;
			}
		}

		return array_values($sourceUserFolderPaths);
	}

	private function getKeyTreePath(string $userFolderPath): string {
		$keyStorageRoot = $this->config->getAppValue('core', 'encryption_key_storage_root', '');
		return Filesystem::normalizePath($keyStorageRoot . '/files_encryption/keys/files' . $userFolderPath);
	}

	private function getEffectiveUserFolderPath(IUser $user, string $defaultUserFolderPath): ?string {
		$userFolderPath = $this->config->getUserValue($user->getUID(), 'collectives', 'user_folder', $defaultUserFolderPath);
		if ($userFolderPath !== '') {
			return $userFolderPath;
		}

		$userLang = $this->l10nFactory->getUserLanguage($user);
		$l10n = $this->l10nFactory->get('collectives', $userLang);
		$translatedFolderName = $l10n->t('Collectives');
		if ($translatedFolderName === '') {
			return null;
		}

		return DIRECTORY_SEPARATOR . '.' . $translatedFolderName;
	}

	private function toggleHiddenMountSegment(string $userFolderPath): ?string {
		if (!str_starts_with($userFolderPath, DIRECTORY_SEPARATOR)) {
			return null;
		}

		$trimmedPath = trim($userFolderPath, DIRECTORY_SEPARATOR);
		if ($trimmedPath === '') {
			return null;
		}

		$segments = explode(DIRECTORY_SEPARATOR, $trimmedPath);
		$mountSegmentIndex = count($segments) - 1;
		$mountSegment = $segments[$mountSegmentIndex];
		if ($mountSegment === '' || $mountSegment === '.') {
			return null;
		}

		if (str_starts_with($mountSegment, '.')) {
			$mountSegment = substr($mountSegment, 1);
		} else {
			$mountSegment = '.' . $mountSegment;
		}

		if ($mountSegment === '') {
			return null;
		}

		$segments[$mountSegmentIndex] = $mountSegment;
		return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
	}

	private function mergeMissingTree(
		string $sourcePath,
		string $targetPath,
		UserFolderEncryptionKeyRepairResult $result,
		?callable $report,
	): void {
		if ($this->rootView->file_exists($targetPath) && !$this->rootView->is_dir($targetPath)) {
			$this->warning(
				'Skipping collectives encryption key repair because target is not a directory: ' . $targetPath,
				$result,
				$report,
			);
			return;
		}

		if (!$this->rootView->file_exists($targetPath)) {
			if ($result->isDryRun()) {
				$result->addCreatedDirectory();
				$this->report($report, 'info', 'Would create ' . $targetPath);
			} elseif ($this->rootView->mkdir($targetPath)) {
				$result->addCreatedDirectory();
				$this->report($report, 'info', 'Creating ' . $targetPath);
			} else {
				$this->warning(
					'Failed to create collectives encryption key directory: ' . $targetPath,
					$result,
					$report,
				);
				return;
			}
		}

		foreach ($this->rootView->getDirectoryContent($sourcePath) as $node) {
			$targetChildPath = Filesystem::normalizePath($targetPath . '/' . $node->getName());
			if ($node->getType() === FileInfo::TYPE_FOLDER) {
				$this->mergeMissingTree($node->getPath(), $targetChildPath, $result, $report);
				continue;
			}

			if ($this->rootView->file_exists($targetChildPath)) {
				$result->addExistingFileSkipped();
				continue;
			}

			if ($result->isDryRun()) {
				$result->addCopiedFile();
				$this->report($report, 'info', 'Would copy ' . $node->getPath() . ' -> ' . $targetChildPath);
				continue;
			}

			if ($this->rootView->copy($node->getPath(), $targetChildPath)) {
				$result->addCopiedFile();
				$this->report($report, 'info', 'Copying ' . $node->getPath() . ' -> ' . $targetChildPath);
			} else {
				$this->warning(
					'Failed to copy collectives encryption key file from ' . $node->getPath() . ' to ' . $targetChildPath,
					$result,
					$report,
				);
			}
		}
	}

	private function warning(string $message, UserFolderEncryptionKeyRepairResult $result, ?callable $report): void {
		$result->addWarning($message);
		$this->report($report, 'warning', $message);
	}

	private function report(?callable $report, string $level, string $message): void {
		if ($report !== null) {
			$report($level, $message);
		}
	}
}
