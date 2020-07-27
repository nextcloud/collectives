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

			if ([] === $folders = $this->root->getById($w->getFolderId())) {
				// TODO: Decide what to do with missing wiki folders
				throw new NotFoundException('Error: Wiki folder (FileID ' . $w->getFolderId() . ') not found');
			}

			$wi = new WikiInfo();
			$wi->fromWiki($w);
			$wi->setFolderName($folders[0]->getName());
			$wi->setFolderPath($folders[0]->getPath());
			$wikis[] = $wi;
		}
		return $wikis;
	}

	/**
	 * @param string $name
	 * @param string $userId
	 *
	 * @return Wiki
	 * @throws AlreadyExistsException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function createWiki(string $name, string $userId): Wiki {
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
		return $wiki;
	}
}
