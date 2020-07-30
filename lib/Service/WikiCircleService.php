<?php

namespace OCA\Wiki\Service;

use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Model\BaseMember;
use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Model\WikiInfo;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\QueryException;
use OCP\Constants;
use OCP\Files\AlreadyExistsException;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\NotPermittedException;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\IManager;
use OCP\Share\IShare;
use Ramsey\Uuid\Uuid;

class WikiCircleService {
	/** @var IRootFolder */
	private $root;
	/** @var WikiMapper */
	private $wikiMapper;
	/** @var IManager */
	private $shareManager;

	public function __construct(
		IRootFolder $root,
		WikiMapper $wikiMapper,
		IManager $shareManager
	) {
		$this->root = $root;
		$this->wikiMapper = $wikiMapper;
		$this->shareManager = $shareManager;
	}

	/**
	 * @param string $userId
	 *
	 * @return array
	 * @throws QueryException
	 * @throws NotFoundException
	 */
	public function getWikis(string $userId): array {
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
	 * @param string $userId
	 * @param string $name
	 *
	 * @return WikiInfo
	 * @throws AlreadyExistsException
	 * @throws NotPermittedException
	 * @throws OCSException
	 */
	public function createWiki(string $userId, string $name): WikiInfo {
		// TODO: Create a hidden WikiCircle user
		// TODO: Share new folder with the circle

		// Create a new folder for the wiki
		$wikiPath= '/' . $userId . '/files/' . 'Wiki_' . $name;
		if ($this->root->nodeExists($wikiPath)) {
			throw new AlreadyExistsException($wikiPath . ' already exists');
		}

		$folder = $this->root->newFolder($wikiPath);
		if (!($folder instanceof Folder)) {
			throw new \RuntimeException($wikiPath . ' is not a folder');
		}

		// Create a new secret circle
		$uuid = strtolower(Uuid::uuid4()->toString());
		$circleName = 'wiki@' . $name . '@' . $uuid;
		try {
			$circle = Circles::createCircle(2, $circleName);
		} catch (QueryException $e) {
			throw new \RuntimeException('Failed to create Circle ' . $circleName);
		}

		// Create wiki object
		$wiki = new Wiki();
		$wiki->setCircleUniqueId($circle->getUniqueId());
		$wiki->setFolderId($folder->getId());
		$wiki->setOwnerId($userId);
		$this->wikiMapper->insert($wiki);

		$wi = new WikiInfo();
		$wi->fromWiki($wiki, $folder);

		// Share folder with circle
		$share = $this->shareManager->newShare();
		$share->setNode($folder);
		try {
			$folder->lock(ILockingProvider::LOCK_SHARED);
		} catch (LockedException $e) {
			throw new OCSException('Could not create share for ' . $folder->getName());
		}
		$share->setSharedWith($circle->getUniqueId());
		$share->setPermissions(Constants::PERMISSION_ALL);
		$share->setShareType(IShare::TYPE_CIRCLE);
		$share->setSharedBy($userId);


		try {
			$this->shareManager->createShare($share);
		} catch (\Exception $e) {
			throw new OCSException('Could not create share for ' . $folder->getName());
		} finally {
			$folder->unlock(ILockingProvider::LOCK_SHARED);
		}

		return $wi;
	}

	/**
	 * @param string $userId
	 * @param int    $id
	 *
	 * @return WikiInfo
	 * @throws NotFoundException
	 */
	public function deleteWiki(string $userId, int $id): WikiInfo {
		if (null === $wiki = $this->wikiMapper->findById($id)) {
			throw new NotFoundException('Failed to delete wiki, not found: ' . $id);
		}

		try {
			$circleMember = Circles::getMember($wiki->getCircleUniqueId(), $userId, BaseMember::TYPE_USER, true);
			if ($userId !== $circleMember->getUserId()) {
				throw new NotFoundException('Failed to delete wiki, not found: ' . $id);
			}
			Circles::destroyCircle($wiki->getCircleUniqueId());
		} catch (QueryException $e) {
			throw new NotFoundException('Failed to delete wiki (circle not deleted): ' . $id);
		}

		$wiki = $this->wikiMapper->delete($wiki);
		$folder = $this->findWikiFolder($wiki->getFolderId());
		try {
			$folder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | NotPermittedException $e) {
			throw new NotFoundException('Failed to delete wiki folder: ' . $id);
		}

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
