<?php

namespace OCA\Wiki\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Model\WikiInfo;
use OCP\AppFramework\QueryException;
use OCP\Files\AlreadyExistsException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;

class WikiCircleService {
	private $root;
	private $wikiMapper;

	public function __construct(
		IRootFolder $root,
		WikiMapper $wikiMapper
	) {
		$this->root = $root;
		$this->wikiMapper = $wikiMapper;
	}

	/**
	 * @return array
	 * @throws QueryException
	 * @throws NotFoundException
	 */
	public function getWikis(): array {
		$wikis = [];
		$joinedCircles = Circles::joinedCircles();
		foreach ($joinedCircles as $jc) {
			if (null === $w = $this->wikiMapper->findByCircleId($jc->getUniqueId())) {
				continue;
			}

			$wi = new WikiInfo();
			$wi->fromWiki($w, $folder = $this->findWikiFolder($w->getFolderId()));
			$wikis[] = $wi;
		}
		return $wikis;
	}

	/**
	 * @param string $name
	 * @param string $userId
	 *
	 * @return WikiInfo
	 * @throws AlreadyExistsException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function createWiki(string $name, string $userId): WikiInfo {
		// TODO: Create a hidden WikiCircle user

		// Create a new folder for the wiki
		$wikiPath= '/' . $userId . '/files/' . 'Wiki_' . $name;
		if ($this->root->nodeExists($wikiPath)) {
			throw new AlreadyExistsException($wikiPath.' already exists');
		}

		// Create a new secret circle
		$circle = Circles::createCircle(2, $name);

		$folder = $this->root->newFolder($wikiPath);
		if (!($folder instanceof Folder)) {
			throw new \Exception($wikiPath.' is not a folder');
		}

		$wiki = new Wiki();
		$wiki->setCircleUniqueId($circle->getUniqueId());
		$wiki->setFolderId($folder->getId());
		$wiki->setOwnerId($userId);

		$this->wikiMapper->insert($wiki);

		$wi = new WikiInfo();
		$wi->fromWiki($wiki, $folder);
		return $wi;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return WikiInfo
	 * @throws NotFoundException
	 */
	public function deleteWiki(int $id, string $userId): WikiInfo {
		if (null === $wiki = $this->wikiMapper->findById($id)) {
			throw new NotFoundException('Failed to delete wiki, not found: ' . $id);
		}

		Circles::destroyCircle($wiki->getCircleUniqueId());

		$wiki = $this->wikiMapper->delete($wiki);
		$wi = new WikiInfo();
		$wi->fromWiki($wiki);
		return $wi;
	}

	/**
	 * @param int $folderId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 */
	private function findWikiFolder(int $folderId): Folder {
		$folders = $this->root->getById($folderId);
		if ([] === $folders || !($folders[0] instanceof Folder)) {
			// TODO: Decide what to do with missing wiki folders
			throw new NotFoundException('Error: Wiki folder (FileID ' . $folderId . ') not found');
		}

		return $folders[0];
	}
}
