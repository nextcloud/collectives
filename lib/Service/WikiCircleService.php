<?php

namespace OCA\Wiki\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Model\WikiCircle;
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
	 */
	public function getCircles(): array {
		$circles = [];
		$joinedCircles = Circles::joinedCircles();
		foreach ($joinedCircles as $jc) {
			$c = new WikiCircle();
			$c->setUniqueId($jc->getUniqueId());
			$c->setName($jc->getName());
			if ($cw = $this->wikiMapper->findByCircleId($jc->getUniqueId())) {
				$c->setFileId($cw->getFileId());
			}
			$circles[] = $c;
		}
		return $circles;
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

		// Create a new secret circle
		$circle = Circles::createCircle(2, $name);

		// Create a new folder for the wiki
		$wikiPath= '/' . $userId . '/files/' . 'Wiki_' . $name;
		if ($this->root->nodeExists($wikiPath)) {
			throw new AlreadyExistsException($wikiPath.' already exists');
		}

		$folder = $this->root->newFolder($wikiPath);
		if (!($folder instanceof Folder)) {
			throw new \Exception($wikiPath.' is not a folder');
		}

		$wiki = new Wiki();
		$wiki->setCircleUniqueId($circle->getUniqueId());
		$wiki->setFileId($folder->getId());
		$wiki->setOwnerId($userId);

		$this->wikiMapper->insert($wiki);
		return $wiki;
	}
}
