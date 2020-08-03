<?php

namespace OCA\Wiki\Model;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\Files\File;

/**
 * Class Page
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
class Page extends Entity implements JsonSerializable {
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
	 * @param File   $file
	 *
	 * @return static
	 */
	public static function fromFile(File $file): Page {
		$page = new static();
		$page->setId($file->getId());
		$page->setTitle(basename($file->getName(), self::SUFFIX));
		$page->setTimestamp($file->getMTime());
		$page->setSize($file->getSize());
		$page->setFileName($file->getName());
		$page->setFilePath($file->getPath());
		return $page;
	}
}
