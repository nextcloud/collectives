<?php

namespace OCA\Collectives\Mount;

use OC\Files\Mount\MountPoint;
use OCP\Files\Storage\IStorageFactory;

class CollectiveMountPoint extends MountPoint {
	/** @var int */
	private $folderId;

	/** @var CollectiveRootPathHelper */
	private $collectiveRootPathHelper;

	/**
	 * CollectiveMountPoint constructor.
	 *
	 * @param int|null                 $folderId
	 * @param CollectiveRootPathHelper $collectiveRootPathHelper
	 * @param CollectiveStorage        $storage
	 * @param string                   $mountPoint
	 * @param array|null               $arguments
	 * @param IStorageFactory|null     $loader
	 * @param array|null               $mountOptions
	 * @param int|null                 $mountId
	 *
	 * @throws \Exception
	 */
	public function __construct(?int $folderId,
								CollectiveRootPathHelper $collectiveRootPathHelper,
								CollectiveStorage $storage,
								string $mountPoint,
								array $arguments = null,
								IStorageFactory $loader = null,
								array $mountOptions = null,
								int $mountId = null) {
		$this->folderId = $folderId;
		$this->collectiveRootPathHelper = $collectiveRootPathHelper;
		parent::__construct($storage, $mountPoint, $arguments, $loader, $mountOptions, $mountId);
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
		return '/' . $this->collectiveRootPathHelper->get() . '/' . $this->getFolderId();
	}
}
