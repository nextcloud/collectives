<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Model\CollectiveShareInfo;
use OCA\Collectives\Model\PageInfo;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\Files\Folder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IL10N;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;

class CollectiveShareService {
	private IShareManager $shareManager;
	private UserFolderHelper $userFolderHelper;
	private CollectiveShareMapper $collectiveShareMapper;
	private PageService $pageService;
	private IL10N $l10n;

	public function __construct(IShareManager $shareManager,
		UserFolderHelper $userFolderHelper,
		CollectiveShareMapper $collectiveShareMapper,
		PageService $pageService,
		IL10N $l10n) {
		$this->shareManager = $shareManager;
		$this->userFolderHelper = $userFolderHelper;
		$this->collectiveShareMapper = $collectiveShareMapper;
		$this->pageService = $pageService;
		$this->l10n = $l10n;
	}

	/**
	 * Use a share link to grant access to collectives folder
	 *
	 * @param string $userId
	 * @param string $collectiveName
	 * @param int    $nodeId
	 *
	 * @return IShare
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function createFolderShare(string $userId, string $collectiveName, int $nodeId): IShare {
		$share = $this->shareManager->newShare();

		$permissions = Constants::PERMISSION_READ;
		$share->setPermissions($permissions);

		// Can we even share link?
		if (!$this->shareManager->shareApiAllowLinks()) {
			throw new NotFoundException('Public link sharing is disabled by the administrator');
		}

		$userFolder = $this->userFolderHelper->get($userId);
		try {
			$path = $userFolder->get($collectiveName);
			if (!($path instanceof Folder)) {
				throw new FilesNotFoundException();
			}
			if ($nodeId !== 0) {
				$nodes = $path->getById($nodeId);
				if (count($nodes) <= 0) {
					throw new FilesNotFoundException();
				}
				$path = $nodes[0];
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException('Wrong path, collective folder doesn\'t exist', 0, $e);
		}
		$share->setNode($path);

		try {
			$share->getNode()->lock(ILockingProvider::LOCK_SHARED);
		} catch (FilesNotFoundException | LockedException $e) {
			throw new NotFoundException('Could not create share', 0, $e);
		}

		$share->setShareType(IShare::TYPE_LINK);
		$share->setSharedBy($userId);
		$share->setLabel($this->l10n->t('Collective Share'));

		try {
			$share = $this->shareManager->createShare($share);
		} catch (GenericShareException $e) {
			throw new NotFoundException($e->getHint(), 0, $e);
		} catch (\Exception $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		} finally {
			try {
				$share->getNode()->unlock(ILockingProvider::LOCK_SHARED);
			} catch (FilesNotFoundException $e) {
				throw new NotFoundException('Could not get share', 0, $e);
			} catch (LockedException $e) {
				throw new NotPermittedException('Failed to unlock share', 0, $e);
			}
		}

		return $share;
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $pageId
	 *
	 * @return CollectiveShareInfo|null
	 */
	public function findShare(string $userId, int $collectiveId, int $pageId): ?CollectiveShareInfo {
		try {
			$collectiveShare = $this->collectiveShareMapper->findOneByCollectiveIdAndUser($collectiveId, $pageId, $userId);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | Exception $e) {
			return null;
		}

		try {
			$folderShare = $this->shareManager->getShareByToken($collectiveShare->getToken());
		} catch (ShareNotFound $e) {
			// Corresponding folder share not found, delete the collective share as well.
			$this->collectiveShareMapper->delete($collectiveShare);
			return null;
		}

		return new CollectiveShareInfo($collectiveShare, $this->isShareEditable($folderShare));
	}

	/**
	 * @param string $token
	 *
	 * @return CollectiveShareInfo|null
	 */
	public function findShareByToken(string $token): ?CollectiveShareInfo {
		try {
			$collectiveShare = $this->collectiveShareMapper->findOneByToken($token);
		} catch (DoesNotExistException | MultipleObjectsReturnedException | Exception $e) {
			return null;
		}

		try {
			$folderShare = $this->shareManager->getShareByToken($collectiveShare->getToken());
		} catch (ShareNotFound $e) {
			// Corresponding folder share not found, delete the collective share as well.
			$this->collectiveShareMapper->delete($collectiveShare);
			return null;
		}

		return new CollectiveShareInfo($collectiveShare, $this->isShareEditable($folderShare));
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 *
	 * @return array
	 */
	public function getSharesByCollectiveAndUser(string $userId, int $collectiveId): array {
		$collectiveShares = $this->collectiveShareMapper->findByCollectiveIdAndUser($collectiveId, $userId);

		$shares = [];
		foreach ($collectiveShares as $share) {
			try {
				$folderShare = $this->shareManager->getShareByToken($share->getToken());
			} catch (ShareNotFound $e) {
				// Corresponding folder share not found, delete the collective share as well.
				$this->collectiveShareMapper->delete($share);
			}
			$shares[] = new CollectiveShareInfo($share, $this->isShareEditable($folderShare));
		}

		return $shares;
	}

	/**
	 * @param IShare $folderShare
	 *
	 * @return bool
	 */
	private function isShareEditable(IShare $folderShare): bool {
		$folderShare->getPermissions();
		return ($folderShare->getPermissions() & Collective::editPermissions) === Collective::editPermissions;
	}

	/**
	 * @param string         $userId
	 * @param CollectiveInfo $collective
	 * @param PageInfo|null  $pageInfo
	 *
	 * @return CollectiveShareInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function createShare(string $userId, CollectiveInfo $collective, ?PageInfo $pageInfo): CollectiveShareInfo {
		if (!$collective->canShare()) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to share %s', $collective->getName()));
		}

		$pageId = 0;
		$nodeId = 0;
		if ($pageInfo) {
			$pageId = $pageInfo->getId();
			$file = $this->pageService->getPageFile($collective->getId(), $pageId, $userId);
			$nodeId = $file->getParent()->getId();
		}

		if (null !== $this->findShare($userId, $collective->getId(), $nodeId)) {
			throw new NotPermittedException($this->l10n->t('A share for collective %s exists already', $collective->getName()));
		}

		$folderShare = $this->createFolderShare($userId, $collective->getName(), $nodeId);

		try {
			return new CollectiveShareInfo($this->collectiveShareMapper->create($collective->getId(), $pageId, $folderShare->getToken(), $userId));
		} catch (Exception $e) {
			throw new NotPermittedException('Failed to create collective/page share for ' . $collective->getName(), 0, $e);
		}
	}

	/**
	 * @param string         $userId
	 * @param CollectiveInfo $collective
	 * @param PageInfo|null  $pageInfo
	 * @param string         $token
	 * @param bool           $editable
	 *
	 * @return CollectiveShareInfo
	 * @throws DoesNotExistException
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function updateShare(string $userId, CollectiveInfo $collective, ?PageInfo $pageInfo, string $token, bool $editable = false): CollectiveShareInfo {
		if (!$collective->canShare()) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to share %s', $collective->getName()));
		}

		if (!$collective->canEdit()) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to edit %s', $collective->getName()));
		}

		$pageId = 0;
		if ($pageInfo) {
			$pageId = $pageInfo->getId();
		}

		if (null === $share = $this->collectiveShareMapper->findOneByCollectiveIdAndTokenAndUser($collective->getId(), $pageId, $token, $userId)) {
			throw new NotFoundException($this->l10n->t('Share not found for user'));
		}

		try {
			$folderShare = $this->shareManager->getShareByToken($token);
		} catch (ShareNotFound $e) {
			throw new NotFoundException($this->l10n->t('Share not found for user'), 0, $e);
		}

		$permissions = Constants::PERMISSION_READ;
		if ($editable) {
			$permissions |= Collective::editPermissions;
		}

		$folderShare->setPermissions($permissions);
		$this->shareManager->updateShare($folderShare);

		return new CollectiveShareInfo($share, $this->isShareEditable($folderShare));
	}

	/**
	 * @param string $token
	 */
	private function deleteFileShare(string $token): void {
		try {
			$share = $this->shareManager->getShareByToken($token);
			$this->shareManager->deleteShare($share);
		} catch (ShareNotFound $e) {
			// Corresponding folder share is already gone.
		}
	}

	/**
	 * @param string $userId
	 * @param int    $collectiveId
	 * @param int    $pageId
	 * @param string $token
	 *
	 * @return CollectiveShareInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function deleteShare(string $userId, int $collectiveId, int $pageId, string $token): CollectiveShareInfo {
		try {
			$collectiveShare = $this->collectiveShareMapper->findOneByCollectiveIdAndTokenAndUser($collectiveId, $pageId, $token, $userId);
			$this->collectiveShareMapper->delete($collectiveShare);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			throw new NotFoundException('Failed to find collective share ' . $token, 0, $e);
		} catch (Exception $e) {
			throw new NotPermittedException('Failed to delete collective share ' . $token, 0, $e);
		}

		$this->deleteFileShare($collectiveShare->getToken());

		return new CollectiveShareInfo($collectiveShare);
	}

	/**
	 * @param int $collectiveId
	 *
	 * @throws NotPermittedException
	 */
	public function deleteShareByCollectiveId(int $collectiveId): void {
		try {
			$collectiveShares = $this->collectiveShareMapper->findByCollectiveId($collectiveId);
			foreach ($collectiveShares as $collectiveShare) {
				$this->collectiveShareMapper->delete($collectiveShare);
				$this->deleteFileShare($collectiveShare->getToken());
			}
		} catch (Exception $e) {
			throw new NotPermittedException('Failed to delete collective share for ' . $collectiveId, 0, $e);
		}
	}
}
