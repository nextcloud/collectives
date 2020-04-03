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
 * @method string getContent()
 * @method void setContent(string $value)
 */
class Page extends Entity implements JsonSerializable {
	private const SUFFIX = '.md';

	protected $title;
	protected $content;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'content' => $this->content
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
		$page->setContent($file->getContent());
		return $page;
	}
}
