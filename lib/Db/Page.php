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
 * @method string getUserId()
 * @method void setUserId(string $value)
 */
class Page extends Entity implements JsonSerializable {
	private const SUFFIX = '.md';

	protected $title;
	protected $content;
	protected $userId;

	public function jsonSerialize() {
		return [
			'id' => $this->id,
			'title' => $this->title,
			'content' => $this->content
		];
	}

	/**
	 * @param File   $file
	 * @param Folder $pagesFolder
	 * @param string $userId
	 *
	 * @return static
	 * @throws \OCP\Files\InvalidPathException
	 * @throws \OCP\Files\NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 * @throws \OCP\Lock\LockedException
	 */
	public static function fromFile(File $file, Folder $pagesFolder, string $userId): Page {
		$page = new static();
		$page->setId($file->getId());
		$page->setTitle(basename($file->getName(), self::SUFFIX));
		$page->setContent($file->getContent());
		$page->setUserId($userId);
		return $page;
	}
}
