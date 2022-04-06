<?php

namespace OCA\Collectives\Mount;

use OC\Files\Mount\MountPoint;
use OC\Files\Storage\Storage;
use OCP\Files\Storage\IStorageFactory;

class CollectiveMountPoint extends MountPoint {
	/** @var int */
	private $folderId;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/**
	 * CollectiveMountPoint constructor.
	 *
	 * @param int|null                $folderId
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param Storage                 $storage
	 * @param string                  $mountPoint
	 * @param array|null              $arguments
	 * @param IStorageFactory|null    $loader
	 * @param array|null              $mountOptions
	 * @param int|null                $mountId
	 *
	 * @throws \Exception
	 */
	public function __construct(?int $folderId,
								CollectiveFolderManager $collectiveFolderManager,
								Storage $storage,
								string $mountPoint,
								array $arguments = null,
								IStorageFactory $loader = null,
								array $mountOptions = null,
								int $mountId = null) {
		$this->folderId = $folderId;
		$this->collectiveFolderManager = $collectiveFolderManager;
		parent::__construct($storage, $mountPoint, $arguments, $loader, $mountOptions, $mountId, MountProvider::class);
	}

	/**
	 * @return string
	 */
	public function getMountType(): string {
		return 'collective';
	}

	/**
	 * @param string $name
	 * @param mixed  $default
	 *
	 * @return mixed
	 */
	public function getOption($name, $default) {
		$options = $this->getOptions();
		return $options[$name] ?? $default;
	}

	/**
	 * @return array
	 */
	public function getOptions(): array {
		$options = parent::getOptions();
		$options['encrypt'] = false;
		return $options;
	}

	/**
	 * @return int
	 */
	public function getFolderId(): int {
		return $this->folderId;
	}

	/**
	 * @return string
	 */
	public function getSourcePath(): string {
		return '/' . $this->collectiveFolderManager->getRootFolder()->getPath() . '/' . $this->getFolderId();
	}
}
