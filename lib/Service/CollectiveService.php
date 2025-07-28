<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OC;
use OC\Files\Node\File;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Db\TagMapper;
use OCA\Collectives\Fs\NodeHelper;
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
use Symfony\Component\String\Slugger\SluggerInterface;

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
		private TagMapper $tagMapper,
		private IL10N $l10n,
		private IEventDispatcher $eventDispatcher,
		private NodeHelper $nodeHelper,
		private SluggerInterface $slugger,
	) {
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
	public function getCollectiveWithShare(int $id, string $userId, ?string $shareToken = null): Collective {
		$collective = $this->getCollective($id, $userId);
		if ($shareToken && null !== $share = $this->shareService->findShareByToken($shareToken)) {
			if ($collective->getId() === $share->getCollectiveId()) {
				$collective->setShareToken($share->getToken());
				$collective->setIsPageShare($share->getPageId() !== 0);
				$collective->setSharePageId($share->getPageId());
				$collective->setShareEditable($share->getEditable());
			} else {
				throw new NotFoundException('Share token does not match collective.');
			}
		}

		return $collective;
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
		$collectives = $this->collectiveHelper->getCollectivesForUser($userId);
		foreach ($collectives as $c) {
			$c->setCanLeave($this->circleHelper->canLeave($c->getCircleId(), $userId));
			if (null !== $share = $this->shareService->findShare($userId, $c->getId(), 0)) {
				$c->setShareToken($share->getToken());
				$c->setShareEditable($share->getEditable());
			}
		}

		return $collectives;
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
	public function findCollectiveByName(string $userId, string $name): Collective {
		$collectives = $this->getCollectives($userId);
		$collectives = array_filter($collectives, static fn (Collective $c) => $c->getName() === $name);
		if ($collectives === []) {
			throw new NotFoundException('Unable to find a collective from its name');
		}
		return end($collectives);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findCollectiveByShare(string $shareToken): Collective {
		if (null === $share = $this->shareService->findShareByToken($shareToken, false)) {
			throw new NotFoundException('Unable to find a collective from its share token');
		}

		if (null === $collective = $this->collectiveMapper->findByIdAndUser($share->getCollectiveId())) {
			throw new NotFoundException('Unable to find a collective from its share token');
		}

		$circle = $this->circleHelper->getCircle($collective->getCircleId(), null, true);
		$collective->setName($circle->getSanitizedName());
		$collective->setShareToken($share->getToken());
		$collective->setIsPageShare($share->getPageId() !== 0);
		$collective->setSharePageId($share->getPageId());
		return $collective;
	}

	public function getCollectiveNameWithEmoji(Collective $collective): string {
		$emoji = $collective->getEmoji();
		return $emoji
			? $emoji . ' ' . $collective->getName()
			: $collective->getName();
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
		string $name,
		?string $emoji = null): array {
		$safeName = $this->nodeHelper->sanitiseFilename($name);

		if ($safeName === '') {
			throw new UnprocessableEntityException('Empty collective name is not allowed');
		}

		// Create a new team
		$message = '';
		try {
			$circle = $this->circleHelper->createCircle($safeName, $userId);
		} catch (CircleExistsException $e) {
			$circle = $this->circleHelper->findCircle($safeName, $userId, Member::LEVEL_ADMIN);
			if ($circle === null) {
				// We don't have admin access to the team
				throw $e;
			}
			$this->circleHelper->flagCircleAsAppManaged($circle->getSingleId());
			$message = $this->l10n->t(
				'Created collective "%s" for existing team.',
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

		// Create a collective object
		$collective = new Collective();
		$collective->setCircleId($circle->getSingleId());
		$collective->setPermissions(Collective::defaultPermissions);
		if ($emoji) {
			$collective->setEmoji($emoji);
		}
		$collective = $this->collectiveMapper->insert($collective);

		$slug = $this->slugger->slug($name)->toString();
		$collective->setSlug($slug);
		$this->collectiveMapper->update($collective);

		// Decorate a collective object
		$collective->setName($circle->getSanitizedName());
		$collective->setLevel($this->circleHelper->getLevel($circle->getSingleId(), $userId));

		// Create folder for collective and optionally copy default landing page
		try {
			$collectiveFolder = $this->collectiveFolderManager->initializeFolder($collective->getId(), $userLang);
		} catch (InvalidPathException|FilesNotPermittedException $e) {
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
		} catch (FilesNotFoundException|InvalidPathException $e) {
			throw new NotFoundException($e->getMessage(), 0, $e);
		}

		return [$collective, $message];
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function updateCollective(int $id,
		string $userId,
		?string $emoji = null): Collective {
		$collective = $this->getCollective($id, $userId);

		if (!$this->circleHelper->isAdmin($collective->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		if ($emoji === '') {
			$collective->setEmoji(null);
		} elseif ($emoji) {
			$collective->setEmoji($emoji);
		}

		return $this->collectiveMapper->update($collective);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setPermissionLevel(int $id,
		string $userId,
		int $permissionLevel,
		int $permission): Collective {
		$collective = $this->getCollective($id, $userId);

		if (!$this->circleHelper->isAdmin($collective->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		$collective->updatePermissionLevel($permissionLevel, $permission);

		return $this->collectiveMapper->update($collective);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setPageMode(int $id,
		string $userId,
		int $mode): Collective {
		$collective = $this->getCollective($id, $userId);

		if (!$this->circleHelper->isAdmin($collective->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		$collective->setPageMode($mode);

		return $this->collectiveMapper->update($collective);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function trashCollective(int $id, string $userId): Collective {
		$collective = $this->getCollective($id, $userId);

		if (!$this->circleHelper->isAdmin($collective->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to delete collective: ' . $id);
		}

		// Invalidate mountpoint cache as we changed list of collectives
		if (class_exists(InvalidateMountCacheEvent::class)) {
			$this->eventDispatcher->dispatchTyped(new InvalidateMountCacheEvent(null));
		}

		return $this->collectiveMapper->trash($collective);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function deleteCollective(int $id, string $userId, bool $deleteCircle): Collective {
		$collective = $this->getCollectiveFromTrash($id, $userId);

		if ($deleteCircle) {
			$this->circleHelper->destroyCircle($collective->getCircleId(), $userId);
		} else {
			$this->circleHelper->unflagCircleAsAppManaged($collective->getCircleId());
		}

		// Delete collective folder and its contents
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collective->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException|FilesNotFoundException|FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder', 0, $e);
		} finally {
			// Delete leftovers in any case (also if collective folder is already gone)

			// Delete shares and user settings
			$this->shareService->deleteShareByCollectiveId($collective->getId());
			$this->collectiveUserSettingsMapper->deleteByCollectiveId($collective->getId());

			// Delete tags
			$this->tagMapper->deleteByCollectiveId($collective->getId());

			// Delete page trash for the collective
			$this->initPageTrashBackend();
			if ($this->pageTrashBackend) {
				$this->pageTrashBackend->deleteTrashFolder($collective->getId());
			}

			// Delete page versions for the collective
			$this->initPageVersionsBackend();
			if ($this->pageVersionsBackend) {
				$this->pageVersionsBackend->deleteVersionsFolder($collective->getId());
			}
		}

		return $this->collectiveMapper->delete($collective);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function restoreCollective(int $id, string $userId): Collective {
		$collective = $this->getCollectiveFromTrash($id, $userId);

		// Invalidate mountpoint cache as we changed list of collectives
		if (class_exists(InvalidateMountCacheEvent::class)) {
			$this->eventDispatcher->dispatchTyped(new InvalidateMountCacheEvent(null));
		}

		return $this->collectiveMapper->restore($collective);
	}
}
