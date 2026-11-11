<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageInfo;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\PublicShareController;
use OCP\Constants;
use OCP\DB\Exception;
use OCP\Files\Folder;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\ISession;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IProviderFactory;
use OCP\Share\IShare;

class CollectiveShareService {
	private array $shareCache = [];

	public function __construct(
		private readonly IShareManager $shareManager,
		private readonly UserFolderHelper $userFolderHelper,
		private readonly CollectiveShareMapper $collectiveShareMapper,
		private readonly CollectiveMapper $collectiveMapper,
		private readonly PageService $pageService,
		private readonly IL10N $l10n,
		private readonly ISession $session,
		private readonly IDateTimeZone $dateTimeZone,
		private readonly IUserManager $userManager,
		private readonly IProviderFactory $shareProviderFactory,
	) {
	}

	/**
	 * Use a share link to grant access to collectives folder
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function createFolderShare(string $userId, string $collectiveName, int $nodeId, ?string $password = null, string $label = ''): IShare {
		$share = $this->shareManager->newShare();

		$permissions = Constants::PERMISSION_READ;
		$share->setPermissions($permissions);

		// Can we even share link?
		if (!$this->shareManager->shareApiAllowLinks()) {
			throw new NotFoundException('Public link sharing is disabled by the administrator');
		}

		$userFolder = $this->userFolderHelper->get($userId);
		try {
			$node = $userFolder->get($collectiveName);
			if (!($node instanceof Folder)) {
				throw new FilesNotFoundException();
			}
			if ($nodeId !== 0) {
				$node = $node->getFirstNodeById($nodeId);
				if ($node === null) {
					throw new FilesNotFoundException();
				}
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException('Wrong path, collective folder doesn\'t exist', 0, $e);
		}
		$share->setNode($node);

		try {
			$share->getNode()->lock(ILockingProvider::LOCK_SHARED);
		} catch (FilesNotFoundException|LockedException $e) {
			throw new NotFoundException('Could not create share', 0, $e);
		}

		$share->setShareType(IShare::TYPE_LINK);
		$share->setSharedBy($userId);
		$share->setLabel($label);
		if ($password) {
			$share->setPassword($password);
		}

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

	public function findShare(string $userId, int $collectiveId, int $pageId): ?CollectiveShare {
		try {
			$collectiveShare = $this->collectiveShareMapper->findOneByCollectiveIdAndUser($collectiveId, $pageId, $userId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception) {
			return null;
		}

		try {
			$folderShare = $this->shareManager->getShareByToken($collectiveShare->getToken());
		} catch (ShareNotFound) {
			$this->deleteIfFolderShareGone($collectiveShare);
			return null;
		}

		$this->setFolderShareAttributes($collectiveShare, $folderShare);
		return $collectiveShare;
	}

	public function findShareByToken(string $token, bool $getPermissionInfo = true): ?CollectiveShare {
		try {
			$collectiveShare = $this->collectiveShareMapper->findOneByToken($token);
		} catch (DoesNotExistException|MultipleObjectsReturnedException|Exception) {
			return null;
		}

		if (!$getPermissionInfo) {
			return $collectiveShare;
		}

		try {
			$folderShare = $this->shareManager->getShareByToken($collectiveShare->getToken());
		} catch (ShareNotFound) {
			// The share manager also hides existing folder shares, e.g. if the owner is
			// disabled or not allowed to create link shares. Don't clean up shares as they
			// might get enabled again later on.
			return null;
		}

		$this->setFolderShareAttributes($collectiveShare, $folderShare);
		return $collectiveShare;
	}

	/**
	 * Get shares of a collective and its pages
	 *
	 * Users with share permission get the shares of all members, others only their own.
	 * Tokens are only returned to users that can delete the share: the owner and collective admins.
	 *
	 * @return list<CollectiveShare>
	 */
	public function getSharesByCollective(string $userId, Collective $collective): array {
		$collectiveShares = $collective->canShare()
			? $this->collectiveShareMapper->findByCollectiveId($collective->getId())
			: $this->collectiveShareMapper->findByCollectiveIdAndUser($collective->getId(), $userId);

		$shares = [];
		foreach ($collectiveShares as $collectiveShare) {
			try {
				$folderShare = $this->shareManager->getShareByToken($collectiveShare->getToken());
			} catch (ShareNotFound) {
				$this->deleteIfFolderShareGone($collectiveShare);
				continue;
			}
			$this->setFolderShareAttributes($collectiveShare, $folderShare, $collective);
			$this->setUserAttributes($collectiveShare, $collective, $userId);
			// Members could otherwise keep public access via other members' links after being removed
			if (!$collectiveShare->getCanDelete()) {
				$collectiveShare->setToken('');
			}
			$shares[] = $collectiveShare;
		}

		return $shares;
	}

	/**
	 * @param Collective|null $collective The collective of the share, looked up if not passed
	 */
	private function setFolderShareAttributes(CollectiveShare $collectiveShare, IShare $folderShare, ?Collective $collective = null): void {
		$collectiveShare->setEditable($this->isShareEditable($folderShare, $collectiveShare, $collective));
		if ($folderShare->getPassword()) {
			$collectiveShare->setPassword($folderShare->getPassword());
		}
		$collectiveShare->setExpirationDate($this->getExpirationDate($folderShare));
		$collectiveShare->setLabel($folderShare->getLabel() ?? '');
	}

	/**
	 * Set the owner display name and the permissions of the requesting user
	 */
	private function setUserAttributes(CollectiveShare $collectiveShare, Collective $collective, string $userId): void {
		$owner = $collectiveShare->getOwner();
		$collectiveShare->setOwnerDisplayName($this->userManager->getDisplayName($owner) ?? $owner);
		$collectiveShare->setCanUpdate($this->canUpdateShare($collectiveShare, $collective, $userId));
		$collectiveShare->setCanDelete($this->canDeleteShare($collectiveShare, $collective, $userId));
	}

	/**
	 * Only the owner can update a share, as long as they're allowed to share and edit the collective
	 */
	private function canUpdateShare(CollectiveShare $collectiveShare, Collective $collective, string $userId): bool {
		return $collectiveShare->getOwner() === $userId
			&& $collective->canShare()
			&& $collective->canEdit();
	}

	/**
	 * The owner and collective admins can delete a share
	 */
	private function canDeleteShare(CollectiveShare $collectiveShare, Collective $collective, string $userId): bool {
		return $collectiveShare->getOwner() === $userId
			|| $collective->getLevel() >= Member::LEVEL_ADMIN;
	}

	/**
	 * Get the expiration date of a folder share in the user's timezone
	 *
	 * The share provider returns it in the server's default timezone.
	 */
	private function getExpirationDate(IShare $folderShare): ?\DateTime {
		$expirationDate = $folderShare->getExpirationDate();
		if ($expirationDate === null) {
			return null;
		}
		return (clone $expirationDate)->setTimezone($this->dateTimeZone->getTimeZone());
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	private function parseExpirationDate(string $date): \DateTime {
		$expirationDate = \DateTime::createFromFormat('!Y-m-d', $date, $this->dateTimeZone->getTimeZone());
		if ($expirationDate === false) {
			throw new UnprocessableEntityException($this->l10n->t('Invalid date. Format must be YYYY-MM-DD'));
		}
		return $expirationDate;
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	private function parseLabel(string $label): string {
		$label = trim($label);
		// Same limit as the files_sharing OCS API, the share label column holds 255 characters
		if (strlen($label) > 255) {
			throw new UnprocessableEntityException($this->l10n->t('Label must not be longer than 255 characters'));
		}
		return $label;
	}

	private function isShareEditable(IShare $folderShare, CollectiveShare $collectiveShare, ?Collective $collective): bool {
		if (($folderShare->getPermissions() & Collective::editPermissions) !== Collective::editPermissions) {
			return false;
		}
		// memberCanEdit() only depends on the collective permissions, not on the user that loaded the collective
		$collective ??= $this->collectiveMapper->findByIdAndUser($collectiveShare->getCollectiveId(), $collectiveShare->getOwner());
		return $collective !== null && $collective->memberCanEdit();
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws UnprocessableEntityException
	 */
	public function createShare(string $userId, Collective $collective, ?PageInfo $pageInfo, ?string $password = null, string $label = ''): CollectiveShare {
		if (!$collective->canShare()) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to share %s', $collective->getName()));
		}

		$label = $this->parseLabel($label);

		$pageId = 0;
		$nodeId = 0;
		if ($pageInfo) {
			$pageId = $pageInfo->getId();
			$file = $this->pageService->getPageFile($collective->getId(), $pageId, $userId);
			$nodeId = $file->getParent()->getId();
		}

		$folderShare = $this->createFolderShare($userId, $collective->getName(), $nodeId, $password, $label);

		try {
			$collectiveShare = $this->collectiveShareMapper->create($collective->getId(), $pageId, $folderShare->getToken(), $userId);
		} catch (Exception $e) {
			throw new NotPermittedException('Failed to create collective/page share for ' . $collective->getName(), 0, $e);
		}

		$this->setFolderShareAttributes($collectiveShare, $folderShare, $collective);
		$this->setUserAttributes($collectiveShare, $collective, $userId);
		return $collectiveShare;
	}

	/**
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws UnprocessableEntityException
	 */
	public function updateShare(string $userId, Collective $collective, ?PageInfo $pageInfo, string $token, bool $editable, ?string $password = null, ?string $expirationDate = null, ?string $label = null): CollectiveShare {
		if (!$collective->canShare()) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to share %s', $collective->getName()));
		}

		if (!$collective->canEdit()) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to edit %s', $collective->getName()));
		}

		if ($editable && !$collective->memberCanEdit()) {
			throw new NotPermittedException($this->l10n->t('Share links of %s cannot be editable because editing is limited to admins or moderators', $collective->getName()));
		}

		if ($label !== null) {
			$label = $this->parseLabel($label);
		}

		$pageId = 0;
		if ($pageInfo) {
			$pageId = $pageInfo->getId();
		}

		$share = $this->findShareInCollective($collective, $pageId, $token);
		if (!$this->canUpdateShare($share, $collective, $userId)) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to update this share'));
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
		if ($password !== null) {
			if ($password === '') {
				// With enforced password policy, empty password strings fail to verify
				$folderShare->setPassword(null);
			} else {
				$folderShare->setPassword($password);
			}
		}

		if ($expirationDate !== null) {
			$folderShare->setExpirationDate($expirationDate === '' ? null : $this->parseExpirationDate($expirationDate));
		}

		if ($label !== null) {
			$folderShare->setLabel($label);
		}

		try {
			$this->shareManager->updateShare($folderShare);
		} catch (GenericShareException $e) {
			throw new UnprocessableEntityException($e->getHint(), 0, $e);
		} catch (\InvalidArgumentException $e) {
			throw new UnprocessableEntityException($e->getMessage(), 0, $e);
		}

		$this->setFolderShareAttributes($share, $folderShare, $collective);
		$this->setUserAttributes($share, $collective, $userId);
		return $share;
	}

	/**
	 * Get a link share via the link share provider
	 *
	 * Unlike the share manager, the provider doesn't hide link shares, e.g. of disabled owners,
	 * of owners that are not allowed to create link shares or when link sharing is disabled.
	 *
	 * @throws ShareNotFound
	 */
	private function getLinkShare(string $token): IShare {
		return $this->shareProviderFactory->getProviderForType(IShare::TYPE_LINK)->getShareByToken($token);
	}

	private function deleteFileShare(string $token): void {
		try {
			$share = $this->getLinkShare($token);
		} catch (ShareNotFound) {
			// Corresponding folder share is already gone.
			return;
		}
		$this->shareManager->deleteShare($share);
	}

	/**
	 * Delete a collective share if its folder share is gone, not just hidden by the share manager
	 */
	private function deleteIfFolderShareGone(CollectiveShare $collectiveShare): void {
		try {
			$this->getLinkShare($collectiveShare->getToken());
		} catch (ShareNotFound) {
			$this->collectiveShareMapper->delete($collectiveShare);
		}
	}

	/**
	 * @throws Exception
	 * @throws NotFoundException
	 */
	private function findShareInCollective(Collective $collective, int $pageId, string $token): CollectiveShare {
		try {
			return $this->collectiveShareMapper->findOneByCollectiveIdAndToken($collective->getId(), $pageId, $token);
		} catch (DoesNotExistException|MultipleObjectsReturnedException $e) {
			throw new NotFoundException($this->l10n->t('Share not found'), 0, $e);
		}
	}

	/**
	 * @throws Exception
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function deleteShare(string $userId, Collective $collective, int $pageId, string $token): CollectiveShare {
		$collectiveShare = $this->findShareInCollective($collective, $pageId, $token);
		if (!$this->canDeleteShare($collectiveShare, $collective, $userId)) {
			throw new NotPermittedException($this->l10n->t('You are not allowed to delete this share'));
		}

		// Delete the folder share first, so the collective share stays listed if that fails
		$this->deleteFileShare($collectiveShare->getToken());

		try {
			$this->collectiveShareMapper->delete($collectiveShare);
		} catch (Exception $e) {
			throw new NotPermittedException('Failed to delete collective share ' . $token, 0, $e);
		}

		return $collectiveShare;
	}

	/**
	 * @throws NotPermittedException
	 */
	public function deleteShareByCollectiveId(int $collectiveId): void {
		try {
			$collectiveShares = $this->collectiveShareMapper->findByCollectiveId($collectiveId);
			foreach ($collectiveShares as $collectiveShare) {
				$this->deleteFileShare($collectiveShare->getToken());
				$this->collectiveShareMapper->delete($collectiveShare);
			}
		} catch (Exception $e) {
			throw new NotPermittedException('Failed to delete collective share for ' . $collectiveId, 0, $e);
		}
	}

	/**
	 * @throws ShareNotFound
	 */
	public function getShare(string $token): IShare {
		$this->shareCache[$token] ??= $this->shareManager->getShareByToken($token);
		return $this->shareCache[$token];
	}

	public function isShareAuthenticated(string $token): bool {
		try {
			$share = $this->getShare($token);
		} catch (ShareNotFound) {
			return false;
		}

		$passwordHash = $share->getPassword();
		if ($passwordHash === null) {
			return true;
		}

		$allowedTokensJSON = $this->session->get(PublicShareController::DAV_AUTHENTICATED_FRONTEND) ?? '[]';
		$allowedTokens = json_decode($allowedTokensJSON, true);
		if (!is_array($allowedTokens)) {
			$allowedTokens = [];
		}
		return ($allowedTokens[$token] ?? '') === $passwordHash;
	}
}
