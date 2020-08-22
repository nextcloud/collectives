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
 */
class PageFile extends Entity implements JsonSerializable {
	private const SUFFIX = '.md';

	protected $title;
	protected $timestamp;
	protected $size;
	protected $fileName;
	protected $filePath;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'fileName' => $this->fileName,
			'filePath' => $this->filePath
		];
	}

	/**
	 * @param File $file
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function fromFile(File $file): void {
		$this->setId($file->getId());
		$this->setTitle(basename($file->getName(), self::SUFFIX));
		$this->setTimestamp($file->getMTime());
		$this->setSize($file->getSize());
		$this->setFileName($file->getName());
		$this->setFilePath($file->getMountPoint()->getMountPoint() . $file->getInternalPath());
	}
}
