<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Versions;

use OC\Files\View;
use OC\Hooks\BasicEmitter;
use OC\User\User;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IDBConnection;
use OCP\IUser;
use Psr\Container\ContainerInterface;

class CollectiveVersionsExpireManager extends BasicEmitter {
	private ?VersionsBackend $versionsBackend = null;
	private string $dependencyInjectionError = '';

	public function __construct(
		ContainerInterface $appContainer,
		private CollectiveFolderManager $folderManager,
		private ExpireManager $expireManager,
		private IDBConnection $connection,
		private CollectiveMapper $collectiveMapper,
		private ITimeFactory $timeFactory,
		private IEventDispatcher $dispatcher,
	) {
		try {
			$this->versionsBackend = $appContainer->get(VersionsBackend::class);
		} catch (QueryException $e) {
			$this->dependencyInjectionError = $e->getMessage();
		}
	}

	public function getAllFolders(): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('co.id AS id', 'circle_unique_id')
			->from('collectives', 'co');
		$rows = $qb->executeQuery()->fetchAll();

		$folderMap = [];
		try {
			foreach ($rows as $row) {
				$id = (int)$row['id'];
				$folderMap[$id] = [
					'id' => $id,
					'mount_point' => $this->collectiveMapper->circleIdToName($row['circle_unique_id'], null, true),
				];
			}
		} catch (NotFoundException) {
		}

		return $folderMap;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function expireAll(): void {
		$folders = $this->getAllFolders();
		foreach ($folders as $folder) {
			$this->emit(self::class, 'enterFolder', [$folder]);
			$this->expireFolder($folder);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function expireFolder(array $folder): void {
		if (is_null($this->versionsBackend)) {
			throw new MissingDependencyException($this->dependencyInjectionError);
		}

		// TODO: Fix view path
		$view = new View('/' . $this->folderManager->getRootPath() . '/versions/' . $folder['id']);
		try {
			$files = $this->versionsBackend->getAllVersionedFiles($folder);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
		/** @var IUser $dummyUser */
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
				// Some versions could have been lost during move operations across storage.
				// When this is the case, the fileinfo's path will not contain the name.
				// When this is the case, we unlink the version's folder for the fileid, and continue to the next file.
				if (!str_ends_with($file->getPath(), $file->getName())) {
					$view->unlink('/' . $fileId);
					continue;
				}
				try {
					$versions = $this->versionsBackend->getVersionsForFile($dummyUser, $file);
				} catch (FilesNotPermittedException|InvalidPathException $e) {
					throw new NotPermittedException($e->getMessage(), 0, $e);
				}
				$expireVersions = $this->expireManager->getExpiredVersion($versions, $this->timeFactory->getTime(), false);
				foreach ($expireVersions as $version) {
					/** @var CollectiveVersion $version */
					$this->emit(self::class, 'deleteVersion', [$version]);
					$view->unlink('/' . $fileId . '/' . $version->getVersionFile()->getName());
				}
			} else {
				// source file no longer exists
				$this->emit(self::class, 'deleteFile', [$fileId]);
				try {
					$this->versionsBackend->deleteAllVersionsForFile($folder['id'], $fileId);
				} catch (FilesNotPermittedException|InvalidPathException $e) {
					throw new NotPermittedException($e->getMessage(), 0, $e);
				} catch (FilesNotFoundException $e) {
					throw new NotFoundException($e->getMessage(), 0, $e);
				}
			}
		}
	}
}
