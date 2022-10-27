<?php

namespace OCA\Collectives\Model;

use JsonSerializable;

use OCP\AppFramework\Db\Entity;
use OCP\Files\File;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException;

/**
 * @method string getLastUserId()
 * @method void setLastUserId(string $value)
 * @method string getLastUserDisplayName()
 * @method void setLastUserDisplayName(string $value)
 * @method string getEmoji()
 * @method void setEmoji(string $value)
 * @method string getTitle()
 * @method void setSubpageOrder(string $value)
 * @method string getSubpageOrder()
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
 * @method int getParentId()
 * @method void setParentId(int $value)
 * @method int getShareToken()
 * @method void setShareToken(string $value)
 */
class PageInfo extends Entity implements JsonSerializable {
	public const INDEX_PAGE_TITLE = 'Readme';
	public const TEMPLATE_PAGE_TITLE = 'Template';
	public const SUFFIX = '.md';

	protected ?string $lastUserId = null;
	protected ?string $lastUserDisplayName = null;
	protected ?string $emoji = null;
	protected ?string $subpageOrder = null;
	protected ?string $title = null;
	protected ?int $timestamp = null;
	protected ?int $size = null;
	protected ?string $fileName = null;
	protected ?string $filePath = null;
	protected ?string $collectivePath = null;
	protected ?int $parentId = null;
	protected ?string $shareToken = null;

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'lastUserId' => $this->lastUserId,
			'lastUserDisplayName' => $this->lastUserDisplayName,
			'emoji' => $this->emoji,
			'subpageOrder' => json_decode($this->subpageOrder ?? '[]', true, 512, JSON_THROW_ON_ERROR),
			'title' => $this->title,
			'timestamp' => $this->timestamp,
			'size' => $this->size,
			'fileName' => $this->fileName,
			'filePath' => $this->filePath,
			'collectivePath' => $this->collectivePath,
			'parentId' => $this->parentId,
			'shareToken' => $this->shareToken,
		];
	}

	/**
	 * @param File        $file
	 * @param int         $parentId
	 * @param string|null $lastUserId
	 * @param string|null $emoji
	 * @param string|null  $subpageOrder
	 *
	 * @throws InvalidPathException
	 * @throws NotFoundException
	 */
	public function fromFile(File $file, int $parentId, ?string $lastUserId = null, ?string $lastUserDisplayName = null, ?string $emoji = null, ?string $subpageOrder = null): void {
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
		if (null !== $lastUserDisplayName) {
			$this->setLastUserDisplayName($lastUserDisplayName);
		}
		if (null !== $emoji) {
			$this->setEmoji($emoji);
		}
		if (null !== $subpageOrder) {
			$this->setSubpageOrder($subpageOrder);
		}
		$this->setParentId($parentId);
	}
}
