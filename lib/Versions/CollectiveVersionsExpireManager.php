<?php


namespace OCA\Collectives\Versions;

use OC\Files\View;
use OC\Hooks\BasicEmitter;
use OC\User\User;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\FileInfo;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CollectiveVersionsExpireManager extends BasicEmitter {
	/** @var CollectiveFolderManager */
	private $folderManager;

	/** @var ExpireManager */
	private $expireManager;

	/** @var VersionsBackend */
	private $versionsBackend;

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
	 * @param ITimeFactory             $timeFactory
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(CollectiveFolderManager $folderManager,
								ExpireManager $expireManager,
								VersionsBackend $versionsBackend,
								ITimeFactory $timeFactory,
								EventDispatcherInterface $dispatcher) {
		$this->folderManager = $folderManager;
		$this->expireManager = $expireManager;
		$this->versionsBackend = $versionsBackend;
		$this->timeFactory = $timeFactory;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function expireAll(): void {
		$folders = $this->folderManager->getAllFolders();
		foreach ($folders as $folder) {
			$this->emit(self::class, 'enterFolder', [$folder]);
			$this->expireFolder($folder);
		}
	}

	/**
	 * @param array $folder
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws \Exception
	 */
	public function expireFolder(array $folder): void {
		// TODO: Fix view path
		$view = new View('/' . $this->folderManager->getRootPath() . '/versions/' . $folder['id']);
		$files = $this->versionsBackend->getAllVersionedFiles($folder);
		$dummyUser = new User('', null, $this->dispatcher);
		foreach ($files as $fileId => $file) {
			if ($file instanceof FileInfo) {
				$versions = $this->versionsBackend->getVersionsForFile($dummyUser, $file);
				$expireVersions = $this->expireManager->getExpiredVersion($versions, $this->timeFactory->getTime(), false);
				foreach ($expireVersions as $version) {
					/** @var CollectiveVersion $version */
					$this->emit(self::class, 'deleteVersion', [$version]);
					$view->unlink('/' . $fileId . '/' . $version->getVersionFile()->getName());
				}
			} else {
				// source file no longer exists
				$this->emit(self::class, 'deleteFile', [$fileId]);
				$this->versionsBackend->deleteAllVersionsForFile($folder['id'], $fileId);
			}
		}
	}
}
