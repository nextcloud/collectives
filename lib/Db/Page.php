<?php

namespace OCA\Wiki\Db;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\Files\File;
use OCP\Files\Folder;

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
 * @method string getFilename()
 * @method void setFilename(string $value)
 * @method string getBasedir()
 * @method void setBasedir(string $value)
 */
class Page extends Entity implements JsonSerializable {
	private const BASEDIR = 'Wiki';
	private const SUFFIX = '.md';

	protected $title;
	protected $timestamp;
	protected $size;
	protected $filename;
	protected $basedir;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'filename' => $this->filename,
			'basedir' => $this->basedir
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
		$page->setFilename($file->getName());
		$page->setBasedir(self::BASEDIR);
		return $page;
	}
}
