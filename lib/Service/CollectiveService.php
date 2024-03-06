<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OC;
use OC\Files\Node\File;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCA\Collectives\Trash\PageTrashBackend;
use OCA\Collectives\Versions\VersionsBackend;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Events\InvalidateMountCacheEvent;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IL10N;

class CollectiveService extends CollectiveServiceBase {
	private ?PageTrashBackend $pageTrashBackend = null;
	private ?VersionsBackend $pageVersionsBackend = null;

	public function __construct(
		private IAppManager $appManager,
		CollectiveMapper $collectiveMapper,
		private CollectiveHelper $collectiveHelper,
		private CollectiveFolderManager $collectiveFolderManager,
		CircleHelper $circleHelper,
		private CollectiveShareService $shareService,
		private CollectiveUserSettingsMapper $collectiveUserSettingsMapper,
		private PageMapper $pageMapper,
		private IL10N $l10n,
		private IEventDispatcher $eventDispatcher) {
		parent::__construct($collectiveMapper, $circleHelper);
	}

	private function initPageTrashBackend(): void {
		if ($this->appManager->isEnabledForUser('files_trashbin')) {
			$this->pageTrashBackend = OC::$server->get(PageTrashBackend::class);
		}
	}

	private function initPageVersionsBackend(): void {
		if ($this->appManager->isEnabledForUser('files_versions')) {
			$this->pageVersionsBackend = OC::$server->get(VersionsBackend::class);
		}
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveWithShare(int $id, string $userId, ?string $shareToken = null): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);
		if ($shareToken && null !== $share = $this->shareService->findShareByToken($shareToken)) {
			if ($collectiveInfo->getId() === $share->getCollectiveId()) {
				$collectiveInfo->setShareToken($share->getToken());
				$collectiveInfo->setIsPageShare($share->getPageId() !== 0);
				$collectiveInfo->setShareEditable($share->getEditable());
			} else {
				throw new NotFoundException('Share token does not match collective.');
			}
		}

		return $collectiveInfo;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveHelper->getCollectivesForUser($userId);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesWithShares(string $userId): array {
		$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($userId);
		foreach ($collectiveInfos as $c) {
			if (null !== $share = $this->shareService->findShare($userId, $c->getId(), 0)) {
				$c->setShareToken($share->getToken());
				$c->setShareEditable($share->getEditable());
			}
		}

		return $collectiveInfos;
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesTrash(string $userId): array {
		return $this->collectiveHelper->getCollectivesTrashForUser($userId);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findCollectiveByName(string $userId, string $name): CollectiveInfo {
		$collectives = $this->getCollectives($userId);
		$collectives = array_filter($collectives, static fn (CollectiveInfo $c) => $c->getName() === $name);
		if ($collectives === []) {
			throw new NotFoundException('Unable to find a collective from its name');
		}
		return end($collectives);
	}

	public function getCollectiveNameWithEmoji(CollectiveInfo $collectiveInfo): string {
		$emoji = $collectiveInfo->getEmoji();
		return $emoji
			? $emoji . ' ' . $collectiveInfo->getName()
			: $collectiveInfo->getName();
	}

	/**
	 * @throws CircleExistsException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws UnprocessableEntityException
	 */
	public function createCollective(string $userId,
		string $userLang,
		string $safeName,
		?string $emoji = null): array {
		if ($safeName === '') {
			throw new UnprocessableEntityException('Empty collective name is not allowed');
		}

		// Create a new circle
		$message = '';
		try {
			$circle = $this->circleHelper->createCircle($safeName, $userId);
		} catch (CircleExistsException $e) {
			$circle = $this->circleHelper->findCircle($safeName, $userId, Member::LEVEL_ADMIN);
			if ($circle === null) {
				// We don't have admin access to the circle
				throw $e;
			}
			$this->circleHelper->flagCircleAsAppManaged($circle->getSingleId());
			$message = $this->l10n->t(
				'Created collective "%s" for existing circle.',
				[$safeName]
			);
		}

		if ($this->collectiveMapper->findByCircleId($circle->getSingleId(), true) !== null) {
			// There's already a collective with that name.
			throw new UnprocessableEntityException('Collective already exists.');
		}

		// Invalidate mountpoint cache as we changed list of collectives
		if (class_exists(InvalidateMountCacheEvent::class)) {
			$this->eventDispatcher->dispatchTyped(new InvalidateMountCacheEvent(null));
		}

		// Create collective object
		$collective = new Collective();
		$collective->setCircleId($circle->getSingleId());
		$collective->setPermissions(Collective::defaultPermissions);
		if ($emoji) {
			$collective->setEmoji($emoji);
		}
		$collective = $this->collectiveMapper->insert($collective);

		// Read collectiveInfo object
		$collectiveInfo = new CollectiveInfo(
			$collective,
			$circle->getSanitizedName(),
			$this->circleHelper->getLevel($circle->getSingleId(), $userId));

		// Create folder for collective and optionally copy default landing page
		try {
			$collectiveFolder = $this->collectiveFolderManager->initializeFolder($collective->getId(), $userLang);
		} catch (InvalidPathException | FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}

		// Register landing page
		try {
			$file = $collectiveFolder->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
			if (!$file instanceof File) {
				throw new NotFoundException('Unable to get landing page for collective');
			}

			$page = new Page();
			$page->setFileId($file->getId());
			$page->setLastUserId($userId);
			$this->pageMapper->updateOrInsert($page);
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return [$collectiveInfo, $message];
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function updateCollective(int $id,
		string $userId,
		?string $emoji = null): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);

		if (!$this->circleHelper->isAdmin($collectiveInfo->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		if ($emoji === '') {
			$collectiveInfo->setEmoji(null);
		} elseif ($emoji) {
			$collectiveInfo->setEmoji($emoji);
		}

		return new CollectiveInfo($this->collectiveMapper->update($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel(),
			$collectiveInfo->getShareToken(),
			$collectiveInfo->getIsPageShare(),
			$collectiveInfo->getShareEditable(),
			$collectiveInfo->getUserPageOrder(),
			$collectiveInfo->getUserShowRecentPages());
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setPermissionLevel(int $id,
		string $userId,
		int $permissionLevel,
		int $permission): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);

		if (!$this->circleHelper->isAdmin($collectiveInfo->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		$collectiveInfo->updatePermissionLevel($permissionLevel, $permission);

		return new CollectiveInfo($this->collectiveMapper->update($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel(),
			$collectiveInfo->getShareToken(),
			$collectiveInfo->getIsPageShare(),
			$collectiveInfo->getShareEditable(),
			$collectiveInfo->getUserPageOrder(),
			$collectiveInfo->getUserShowRecentPages());
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setPageMode(int $id,
		string $userId,
		int $mode): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);

		if (!$this->circleHelper->isAdmin($collectiveInfo->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		$collectiveInfo->setPageMode($mode);

		return new CollectiveInfo($this->collectiveMapper->update($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel(),
			$collectiveInfo->getShareToken(),
			$collectiveInfo->getIsPageShare(),
			$collectiveInfo->getShareEditable(),
			$collectiveInfo->getUserPageOrder(),
			$collectiveInfo->getUserShowRecentPages());
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function trashCollective(int $id, string $userId): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);

		if (!$this->circleHelper->isAdmin($collectiveInfo->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to delete collective: ' . $id);
		}

		// Invalidate mountpoint cache as we changed list of collectives
		if (class_exists(InvalidateMountCacheEvent::class)) {
			$this->eventDispatcher->dispatchTyped(new InvalidateMountCacheEvent(null));
		}

		return new CollectiveInfo($this->collectiveMapper->trash($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel(),
			$collectiveInfo->getShareToken(),
			$collectiveInfo->getIsPageShare(),
			$collectiveInfo->getShareEditable(),
			$collectiveInfo->getUserPageOrder(),
			$collectiveInfo->getUserShowRecentPages());
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function deleteCollective(int $id, string $userId, bool $deleteCircle): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfoFromTrash($id, $userId);

		if ($deleteCircle) {
			$this->circleHelper->destroyCircle($collectiveInfo->getCircleId(), $userId);
		} else {
			$this->circleHelper->unflagCircleAsAppManaged($collectiveInfo->getCircleId());
		}

		// Delete collective folder and its contents
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collectiveInfo->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | FilesNotFoundException | FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder', 0, $e);
		} finally {
			// Delete leftovers in any case (also if collective folder is already gone)

			// Delete shares and user settings
			$this->shareService->deleteShareByCollectiveId($collectiveInfo->getId());
			$this->collectiveUserSettingsMapper->deleteByCollectiveId($collectiveInfo->getId());

			// Delete page trash for the collective
			$this->initPageTrashBackend();
			if ($this->pageTrashBackend) {
				$this->pageTrashBackend->deleteTrashFolder($collectiveInfo->getId());
			}

			// Delete page versions for the collective
			$this->initPageVersionsBackend();
			if ($this->pageVersionsBackend) {
				$this->pageVersionsBackend->deleteVersionsFolder($collectiveInfo->getId());
			}
		}

		return new CollectiveInfo($this->collectiveMapper->delete($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel(),
			$collectiveInfo->getShareToken(),
			$collectiveInfo->getIsPageShare(),
			$collectiveInfo->getShareEditable(),
			$collectiveInfo->getUserPageOrder(),
			$collectiveInfo->getUserShowRecentPages());
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function restoreCollective(int $id, string $userId): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfoFromTrash($id, $userId);

		// Invalidate mountpoint cache as we changed list of collectives
		if (class_exists(InvalidateMountCacheEvent::class)) {
			$this->eventDispatcher->dispatchTyped(new InvalidateMountCacheEvent(null));
		}

		return new CollectiveInfo($this->collectiveMapper->restore($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel(),
			$collectiveInfo->getShareToken(),
			$collectiveInfo->getIsPageShare(),
			$collectiveInfo->getShareEditable(),
			$collectiveInfo->getUserPageOrder(),
			$collectiveInfo->getUserShowRecentPages());
	}
}
