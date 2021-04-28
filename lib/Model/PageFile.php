<?php

namespace OCA\Collectives\Model;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

/**
 * Class PageFile
 * @method int getId()
 * @method void setId(int $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method int getTimestamp()
 * @method void setTimestamp(int $value)
 * @method int getSize()
 * @method void setSize(int $value)
 * @method string getFileName()
 * @method void setFileName(string $value)
 * @method string getFilePath()
 * @method void setFilePath(string $value)
 * @method string getCollectivePath()
 * @method void setCollectivePath(string $value)
 * @method string getLastUserId()
 * @method void setLastUserId(string $value)
 */
class PageFile extends Entity implements JsonSerializable {
	public const SUFFIX = '.md';

	/** @var string */
	protected $title;

	/** @var int */
	protected $timestamp;

	/** @var int */
	protected $size;

	/** @var string */
	protected $fileName;

	/** @var string */
	protected $filePath;

	/** @var string */
	protected $collectivePath;

	/** @var string */
	protected $lastUserId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'fileName' => $this->fileName,
			'filePath' => $this->filePath,
			'collectivePath' => $this->collectivePath,
			'lastUserId' => $this->lastUserId,
		];
	}

	/**
	 * @param File        $file
	 * @param string|null $lastUserId
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function fromFile(File $file, ?string $lastUserId = null): void {
		$this->setId($file->getId());
		$this->setTitle(basename($file->getName(), self::SUFFIX));
		$this->setTimestamp($file->getMTime());
		$this->setSize($file->getSize());
		$this->setFileName($file->getName());
		$this->setCollectivePath(explode('/', $file->getMountPoint()->getMountPoint(), 4)[3]);
		$this->setFilePath($file->getInternalPath());
		if (null !== $lastUserId) {
			$this->setLastUserId($lastUserId);
		}
	}
}
