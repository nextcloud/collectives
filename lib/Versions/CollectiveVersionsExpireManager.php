<?php


namespace OCA\Collectives\Versions;

use OC\Files\View;
use OC\Hooks\BasicEmitter;
use OC\User\User;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IDBConnection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CollectiveVersionsExpireManager extends BasicEmitter {
	/** @var CollectiveFolderManager */
	private $folderManager;

	/** @var ExpireManager */
	private $expireManager;

	/** @var VersionsBackend */
	private $versionsBackend;

	/** @var IDBConnection */
	private $connection;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var EventDispatcherInterface */
	private $dispatcher;

	/**
	 * CollectiveVersionsExpireManager constructor.
	 *
	 * @param CollectiveFolderManager  $folderManager
	 * @param ExpireManager            $expireManager
	 * @param VersionsBackend          $versionsBackend
	 * @param IDBConnection            $connection
	 * @param CollectiveMapper         $collectiveMapper
	 * @param ITimeFactory             $timeFactory
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(CollectiveFolderManager $folderManager,
								ExpireManager $expireManager,
								VersionsBackend $versionsBackend,
								IDBConnection $connection,
								CollectiveMapper $collectiveMapper,
								ITimeFactory $timeFactory,
								EventDispatcherInterface $dispatcher) {
		$this->folderManager = $folderManager;
		$this->expireManager = $expireManager;
		$this->versionsBackend = $versionsBackend;
		$this->connection = $connection;
		$this->collectiveMapper = $collectiveMapper;
		$this->timeFactory = $timeFactory;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @return array
	 */
	public function getAllFolders(): array {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('co.id AS id', 'circle_unique_id')
			->from('collectives', 'co');
		$rows = $qb->execute()->fetchAll();

		$folderMap = [];
		try {
			foreach ($rows as $row) {
				$id = (int)$row['id'];
				$folderMap[$id] = [
					'id' => $id,
					'mount_point' => $this->collectiveMapper->circleIdToName($row['circle_unique_id']),
				];
			}
		} catch (NotFoundException | NotPermittedException $e) {
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
	 * @param array $folder
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function expireFolder(array $folder): void {
		// TODO: Fix view path
		$view = new View('/' . $this->folderManager->getRootPath() . '/versions/' . $folder['id']);
		try {
			$files = $this->versionsBackend->getAllVersionedFiles($folder);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
				try {
					$versions = $this->versionsBackend->getVersionsForFile($dummyUser, $file);
				} catch (FilesNotPermittedException | InvalidPathException $e) {
					throw new NotPermittedException($e->getMessage());
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
				} catch (FilesNotPermittedException | InvalidPathException $e) {
					throw new NotPermittedException($e->getMessage());
				} catch (FilesNotFoundException $e) {
					throw new NotFoundException($e->getMessage());
				}
			}
		}
	}
}
