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
 * @method string getParentId()
 * @method void setParentId(string $value)
 * @method int getShareToken()
 * @method void setShareToken(string $value)
 */
class PageFile extends Entity implements JsonSerializable {
	public const INDEX_PAGE_TITLE = 'Readme';
	public const TEMPLATE_PAGE_TITLE = 'Template';
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

	/** @var int */
	protected $parentId;

	/** @var string */
	protected $shareToken;

	/**
	 * @return array
	 */
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
			'parentId' => $this->parentId,
			'shareToken' => $this->shareToken,
		];
	}

	/**
	 * @param File        $file
	 * @param int         $parentId
	 * @param string|null $lastUserId
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function fromFile(File $file, int $parentId, ?string $lastUserId = null): void {
		$this->setId($file->getId());
		// Set folder name as title for all index pages except the collective landing page
		if ($parentId !== 0 && 0 === strcmp($file->getName(), self::INDEX_PAGE_TITLE . self::SUFFIX)) {
			$this->setTitle($file->getParent()->getName());
		} else {
			$this->setTitle(basename($file->getName(), self::SUFFIX));
		}
		$this->setTimestamp($file->getMTime());
		$this->setSize($file->getSize());
		$this->setFileName($file->getName());
		$this->setCollectivePath(rtrim(explode('/', $file->getMountPoint()->getMountPoint(), 4)[3], '/'));
		$this->setFilePath($file->getParent()->getInternalPath());
		if (null !== $lastUserId) {
			$this->setLastUserId($lastUserId);
		}
		$this->setParentId($parentId);
	}
}
