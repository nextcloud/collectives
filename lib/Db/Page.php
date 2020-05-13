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
 */
class Page extends Entity implements JsonSerializable {
	private const SUFFIX = '.md';

	protected $title;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title
		];
	}

	/**
	 * @param File $file
	 *
	 * @return Page
	 */
	public static function fromFile(File $file): Page {
		$page = new static();
		$page->setId($file->getId());
		$page->setTitle(basename($file->getName(), self::SUFFIX));
		return $page;
	}
}
