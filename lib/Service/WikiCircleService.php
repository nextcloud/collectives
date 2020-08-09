<?php

namespace OCA\Wiki\Service;

use OC\User\NoUserException;
use OCA\Circles\Api\v1\Circles;
use OCA\Circles\Model\BaseMember;
use OCA\Wiki\Db\Wiki;
use OCA\Wiki\Db\WikiMapper;
use OCA\Wiki\Fs\NodeHelper;
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

class WikiCircleService {
	/** @var IRootFolder */
	private $root;
	/** @var WikiMapper */
	private $wikiMapper;
	/** @var NodeHelper */
	private $nodeHelper;
	/** @var IManager */
	private $shareManager;

	public function __construct(
		IRootFolder $root,
		WikiMapper $wikiMapper,
		NodeHelper $nodeHelper,
		IManager $shareManager
	) {
		$this->root = $root;
		$this->wikiMapper = $wikiMapper;
		$this->nodeHelper = $nodeHelper;
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
			$wi->fromWiki($w, $jc->getName(), $this->wikiMapper->getWikiFolder($w->getId()));
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
	 * @throws NoUserException
	 */
	public function createWiki(string $userId, string $name): WikiInfo {
		if (empty($name)) {
			throw new \RuntimeException('Empty wiki name is not allowed');
		}

		// TODO: Create a hidden WikiCircle user

		// Create a new folder for the wiki
		$userFolder = $this->root->getUserFolder($userId);
		$safeName = $this->nodeHelper->sanitiseFilename($name);
		$wikiPath = NodeHelper::generateFilename($userFolder, $safeName);

		$folder = $userFolder->newFolder($wikiPath);
		if (!($folder instanceof Folder)) {
			throw new \RuntimeException($wikiPath . ' is not a folder');
		}

		// Create a new secret circle
		try {
			$circle = Circles::createCircle(2, $safeName);
		} catch (QueryException $e) {
			throw new \RuntimeException('Failed to create Circle ' . $safeName);
		}

		// Create wiki object
		$wiki = new Wiki();
		$wiki->setCircleUniqueId($circle->getUniqueId());
		$wiki->setFolderId($folder->getId());
		$wiki->setOwnerId($userId);
		$this->wikiMapper->insert($wiki);

		$wi = new WikiInfo();
		$wi->fromWiki($wiki, $circle->getName(), $folder);

		// Share folder with circle
		$share = $this->shareManager->newShare();
		$share->setNode($folder);
		try {
			$folder->lock(ILockingProvider::LOCK_SHARED);
		} catch (LockedException $e) {
			throw new OCSException('Failed to create lock for ' . $folder->getName() . ': ' . $e->getMessage());
		}
		$share->setSharedWith($circle->getUniqueId());
		$share->setPermissions(Constants::PERMISSION_ALL);
		$share->setShareType(IShare::TYPE_CIRCLE);
		$share->setSharedBy($userId);


		try {
			$this->shareManager->createShare($share);
		} catch (\Exception $e) {
			throw new OCSException('Failed to create share for ' . $folder->getName() . ': ' . $e->getMessage());
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

		$folder = $this->wikiMapper->getWikiFolder($wiki->getId());

		try {
			$circle = Circles::detailsCircle($wiki->getCircleUniqueId());
			$circleMember = Circles::getMember($wiki->getCircleUniqueId(), $userId, BaseMember::TYPE_USER, true);
			if ($userId !== $circleMember->getUserId()) {
				throw new NotFoundException('Failed to delete wiki, not found: ' . $id);
			}
			Circles::destroyCircle($wiki->getCircleUniqueId());
		} catch (QueryException $e) {
			throw new NotFoundException('Failed to delete wiki (circle not deleted): ' . $id);
		}

		$wiki = $this->wikiMapper->delete($wiki);
		try {
			$folder->delete();
		} catch (InvalidPathException | \OCP\Files\NotFoundException | NotPermittedException $e) {
			throw new NotFoundException('Failed to delete wiki folder: ' . $id);
		}

		$wi = new WikiInfo();
		$wi->fromWiki($wiki, $circle->getName());

		return $wi;
	}
}
