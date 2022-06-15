<?php

namespace OCA\Collectives\Service;

use OC\Files\Node\File;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IL10N;

class CollectiveService extends CollectiveServiceBase {
	/** @var CollectiveHelper */
	private $collectiveHelper;

	/** @var CollectiveFolderManager */
	private $collectiveFolderManager;

	/** @var CollectiveShareService */
	private $shareService;

	/** @var PageMapper */
	private $pageMapper;

	/** @var IL10N */
	private $l10n;

	/**
	 * CollectiveService constructor.
	 *
	 * @param CollectiveMapper        $collectiveMapper
	 * @param CollectiveHelper        $collectiveHelper
	 * @param CollectiveFolderManager $collectiveFolderManager
	 * @param CircleHelper            $circleHelper
	 * @param CollectiveShareService  $shareService
	 * @param PageMapper              $pageMapper
	 * @param IL10N                   $l10n
	 */
	public function __construct(
		CollectiveMapper $collectiveMapper,
		CollectiveHelper $collectiveHelper,
		CollectiveFolderManager $collectiveFolderManager,
		CircleHelper $circleHelper,
		CollectiveShareService $shareService,
		PageMapper $pageMapper,
		IL10N $l10n) {
		parent::__construct($collectiveMapper, $circleHelper);
		$this->collectiveHelper = $collectiveHelper;
		$this->collectiveFolderManager = $collectiveFolderManager;
		$this->shareService = $shareService;
		$this->pageMapper = $pageMapper;
		$this->l10n = $l10n;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return CollectiveInfo
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getCollectiveWithShare(int $id, string $userId): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);
		if (null !== $share = $this->shareService->findShare($userId, $id)) {
			$collectiveInfo->setShareToken($share->getToken());
			$collectiveInfo->setShareEditable($share->getEditable());
		}

		return $collectiveInfo;
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectives(string $userId): array {
		return $this->collectiveHelper->getCollectivesForUser($userId);
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesWithShares(string $userId): array {
		$collectiveInfos = $this->collectiveHelper->getCollectivesForUser($userId);
		foreach ($collectiveInfos as $c) {
			if (null !== $share = $this->shareService->findShare($userId, $c->getId())) {
				$c->setShareToken($share->getToken());
				$c->setShareEditable($share->getEditable());
			}
		}

		return $collectiveInfos;
	}

	/**
	 * @param string $userId
	 *
	 * @return CollectiveInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function getCollectivesTrash(string $userId): array {
		return $this->collectiveHelper->getCollectivesTrashForUser($userId);
	}

	/**
	 * @param string      $userId
	 * @param string      $userLang
	 * @param string      $safeName
	 * @param string|null $emoji
	 *
	 * @return array [CollectiveInfo, string]
	 * @throws CircleExistsException
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws UnprocessableEntityException
	 */
	public function createCollective(string $userId,
									 string $userLang,
									 string $safeName,
									 string $emoji = null): array {
		if (empty($safeName)) {
			throw new UnprocessableEntityException('Empty collective name is not allowed');
		}

		// Create a new circle
		$message = '';
		try {
			$circle = $this->circleHelper->createCircle($safeName, $userId);
		} catch (CircleExistsException $e) {
			$circle = $this->circleHelper->findCircle($safeName, $userId, Member::LEVEL_ADMIN);
			if (null === $circle) {
				// We don't have admin access to the circle
				throw $e;
			}
			$message = $this->l10n->t(
				'Created collective "%s" for existing circle.',
				[$safeName]
			);
		}

		if (null !== $this->collectiveMapper->findByCircleId($circle->getSingleId(), true)) {
			// There's already a collective with that name.
			throw new UnprocessableEntityException('Collective already exists.');
		}

		// Create collective object
		$collective = new Collective();
		$collective->setCircleId($circle->getSingleId());
		$collective->setPermissions(Collective::defaultPermissions);
		if ($emoji) {
			$collective->setEmoji($emoji);
		}
		$collective = $this->collectiveMapper->insert($collective);

		// Read in collectiveInfo object
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
	 * @param int         $id
	 * @param string      $userId
	 * @param string|null $emoji
	 * @param int|null    $pageOrder
	 *
	 * @return CollectiveInfo
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function updateCollective(int $id,
									 string $userId,
									 string $emoji = null,
									 int $pageOrder = null): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);

		if (!$this->circleHelper->isAdmin($collectiveInfo->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to update collective: ' . $id);
		}

		if ($emoji) {
			$collectiveInfo->setEmoji($emoji);
		}

		if ($pageOrder) {
			try {
				$collectiveInfo->setPageOrder($pageOrder);
			} catch (\RuntimeException $e) {
				throw new NotPermittedException('Failed to update collective with invalid page order', 0, $e);
			}
		}

		return new CollectiveInfo($this->collectiveMapper->update($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel());
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 * @param int    $permissionLevel
	 * @param int    $permission
	 *
	 * @return CollectiveInfo
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
			$collectiveInfo->getLevel());
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function trashCollective(int $id, string $userId): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfo($id, $userId);

		if (!$this->circleHelper->isAdmin($collectiveInfo->getCircleId(), $userId)) {
			throw new NotPermittedException('Member ' . $userId . ' not allowed to delete collective: ' . $id);
		}

		return new CollectiveInfo($this->collectiveMapper->trash($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel());
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 * @param bool   $deleteCircle
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function deleteCollective(int $id, string $userId, bool $deleteCircle): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfoFromTrash($id, $userId);

		if ($deleteCircle) {
			$this->circleHelper->destroyCircle($collectiveInfo->getCircleId(), $userId);
		}

		// Delete collective folder and its contents
		try {
			$collectiveFolder = $this->collectiveFolderManager->getFolder($collectiveInfo->getId());
			$collectiveFolder->delete();
		} catch (InvalidPathException | FilesNotFoundException | FilesNotPermittedException $e) {
			throw new NotFoundException('Failed to delete collective folder', 0, $e);
		} finally {
			$this->shareService->deleteShareByCollectiveId($collectiveInfo->getId());
		}

		return new CollectiveInfo($this->collectiveMapper->delete($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel());
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return CollectiveInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	public function restoreCollective(int $id, string $userId): CollectiveInfo {
		$collectiveInfo = $this->getCollectiveInfoFromTrash($id, $userId);

		return new CollectiveInfo($this->collectiveMapper->restore($collectiveInfo),
			$collectiveInfo->getName(),
			$collectiveInfo->getLevel());
	}
}
