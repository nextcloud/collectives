<?php

namespace OCA\Collectives\Model;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

/**
 * Class PageFile
 * @method integer getId()
 * @method void setId(integer $value)
 * @method string getTitle()
 * @method void setTitle(string $value)
 * @method string getTimestamp()
 * @method void setTimestamp(int $value)
 * @method string getSize()
 * @method void setSize(int $value)
 * @method string getFileName()
 * @method void setFileName(string $value)
 * @method string getFilePath()
 * @method void setFilePath(string $value)
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
	protected $lastUserId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'fileName' => $this->fileName,
			'filePath' => $this->filePath,
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
		$this->setFilePath($file->getMountPoint()->getMountPoint() . $file->getInternalPath());
		if (null !== $lastUserId) {
			$this->setLastUserId($lastUserId);
		}
	}
}
