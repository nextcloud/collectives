<?php

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Page;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Model\PageInfo;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IConfig;
use OCP\Lock\LockedException;

class PageService {
	private const DEFAULT_PAGE_TITLE = 'New Page';

	/** @var PageMapper */
	private $pageMapper;

	/** @var NodeHelper */
	private $nodeHelper;

	/** @var CollectiveMapper */
	private $collectiveMapper;

	/** @var UserFolderHelper */
	private $userFolderHelper;

	/** @var IConfig */
	private $config;

	/**
	 * PageService constructor.
	 *
	 * @param PageMapper       $pageMapper
	 * @param NodeHelper       $nodeHelper
	 * @param CollectiveMapper $collectiveMapper
	 * @param UserFolderHelper $userFolderHelper
	 * @param IConfig          $config
	 */
	public function __construct(PageMapper $pageMapper,
								NodeHelper $nodeHelper,
								CollectiveMapper $collectiveMapper,
								UserFolderHelper $userFolderHelper,
								IConfig  $config) {
		$this->pageMapper = $pageMapper;
		$this->nodeHelper = $nodeHelper;
		$this->collectiveMapper = $collectiveMapper;
		$this->userFolderHelper = $userFolderHelper;
		$this->config = $config;
	}


	/**
	 * @param string     $userId
	 * @param Collective $collective
	 *
	 * @return Folder
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function getCollectiveFolder(string $userId, Collective $collective): Folder {
		try {
			$collectiveName = $this->collectiveMapper->circleIdToName($collective->getCircleId(), $userId);
			$folder = $this->userFolderHelper->get($userId)->get($collectiveName);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		if (!($folder instanceof Folder)) {
			throw new NotFoundException('Folder not found for collective ' . $collective->getId());
		}
		return $folder;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $fileId
	 *
	 * @return Folder
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getFolder(string $userId, Collective $collective, int $fileId): Folder {
		$collectiveFolder = $this->getCollectiveFolder($userId, $collective);
		if ($fileId === 0) {
			return $collectiveFolder;
		}

		$file = $this->nodeHelper->getFileById($collectiveFolder, $fileId);
		if (!($file instanceof File) || !($file->getParent() instanceof Folder)) {
			throw new NotFoundException('Error getting parent folder for file ' . $fileId . ' in collective ' . $collective->getId());
		}

		return $file->getParent();
	}

	/**
	 * @param File $file
	 *
	 * @return int
	 * @throws NotFoundException
	 */
	private function getParentPageId(File $file): int {
		try {
			if (self::isLandingPage($file)) {
				// Return `0` for landing page
				return 0;
			}

			if (self::isIndexPage($file)) {
				// Go down two levels if index page but not landing page
				return $this->getIndexPageFile($file->getParent()->getParent())->getId();
			}

			return $this->getIndexPageFile($file->getParent())->getId();
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param File $file
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 */
	private function getPageByFile(File $file): PageInfo {
		$pageInfo = new PageInfo();
		try {
			$page = $this->pageMapper->findByFileId($file->getId());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}
		$lastUserId = ($page !== null) ? $page->getLastUserId() : null;
		try {
			$pageInfo->fromFile($file, $this->getParentPageId($file), $lastUserId);
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		}

		return $pageInfo;
	}

	/**
	 * @param string $userId
	 * @param int    $fileId
	 */
	private function updatePage(string $userId, int $fileId): void {
		$page = new Page();
		$page->setFileId($fileId);
		$page->setLastUserId($userId);
		$this->pageMapper->updateOrInsert($page);
	}

	/**
	 * @param string $userId
	 * @param Folder $folder
	 * @param string $filename
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function newPage(string $userId, Folder $folder, string $filename): PageInfo {
		$hasTemplate = self::folderHasSubPage($folder, PageInfo::TEMPLATE_PAGE_TITLE);
		try {
			if ($hasTemplate === 1) {
				$template = $folder->get(PageInfo::TEMPLATE_PAGE_TITLE . PageInfo::SUFFIX);
				$newFile = $template->copy($folder->getPath() . '/' . $filename . PageInfo::SUFFIX);
			} elseif ($hasTemplate === 2) {
				$template = $folder->get(PageInfo::TEMPLATE_PAGE_TITLE);
				$newFolder = $template->copy($folder->getPath() . '/' . $filename);
				if ($newFolder instanceof Folder) {
					$newFile = $newFolder->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
				} else {
					throw new NotFoundException('Failed to get Template folder');
				}
			} else {
				$newFile = $folder->newFile($filename . PageInfo::SUFFIX);
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		$pageInfo = new PageInfo();
		try {
			$pageInfo->fromFile($newFile, $this->getParentPageId($newFile), $userId);
			$this->updatePage($userId, $newFile->getId());
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		}

		return $pageInfo;
	}

	/**
	 * @param File $file
	 *
	 * @return Folder
	 * @throws NotPermittedException
	 */
	public function initSubFolder(File $file): Folder {
		$folder = $file->getParent();
		if (self::isIndexPage($file)) {
			return $folder;
		}

		try {
			$folderName = NodeHelper::generateFilename($folder, basename($file->getName(), PageInfo::SUFFIX));
			$subFolder = $folder->newFolder($folderName);
			$file->move($subFolder->getPath() . '/' . PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		} catch (InvalidPathException | FilesNotFoundException | FilesNotPermittedException | LockedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		return $subFolder;
	}

	/**
	 * @param Folder $folder
	 *
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function revertSubFolders(Folder $folder): void {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof Folder) {
					$this->revertSubFolders($node);
				} elseif ($node instanceof File) {
					// Move index page without subpages into the parent folder (if's not the landing page)
					if (self::isIndexPage($node) && !self::isLandingPage($node) && !$this->pageHasOtherContent($node)) {
						$filename = NodeHelper::generateFilename($folder, $folder->getName(), PageInfo::SUFFIX);
						$node->move($folder->getParent()->getPath() . '/' . $filename . PageInfo::SUFFIX);
						$folder->delete();
						break;
					}
				}
			}
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException | LockedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public static function isPage(File $file): bool {
		$name = $file->getName();
		$length = strlen(PageInfo::SUFFIX);
		return (substr($name, -$length) === PageInfo::SUFFIX);
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public static function isLandingPage(File $file): bool {
		$internalPath = $file->getInternalPath();
		return ($internalPath === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public static function isIndexPage(File $file): bool {
		$name = $file->getName();
		return ($name === PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
	}

	/**
	 * @param Folder $folder
	 *
	 * @return File
	 * @throws NotFoundException
	 */
	private function getIndexPageFile(Folder $folder): File {
		try {
			$file = $folder->get(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		if (!($file instanceof File)) {
			throw new NotFoundException('Failed to get index page');
		}

		return $file;
	}

	/**
	 * @param File $file
	 *
	 * @return bool
	 */
	public function pageHasOtherContent(File $file): bool {
		try {
			foreach ($file->getParent()->getDirectoryListing() as $node) {
				if ($node instanceof File &&
					self::isPage($node) &&
					!self::isIndexPage($node)) {
					return true;
				}
				if ($node->getName() !== PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX) {
					return true;
				}
			}
		} catch (FilesNotFoundException $e) {
		}

		return false;
	}

	/**
	 * @param Folder $folder
	 *
	 * @return bool
	 */
	public static function folderHasSubPages(Folder $folder): bool {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File &&
					self::isPage($node) &&
					!self::isIndexPage($node)) {
					return true;
				}

				if ($node instanceof Folder) {
					return self::folderHasSubPages($node);
				}
			}
		} catch (FilesNotFoundException $e) {
		}

		return false;
	}

	/**
	 * @param Folder $folder
	 * @param string $title
	 *
	 * @return int
	 */
	public static function folderHasSubPage(Folder $folder, string $title): int {
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File &&
					strcmp($node->getName(), $title . PageInfo::SUFFIX) === 0) {
					return 1;
				}

				if ($node instanceof Folder &&
					strcmp($node->getName(), $title) === 0 &&
					$node->nodeExists(PageInfo::INDEX_PAGE_TITLE . PageInfo::SUFFIX)) {
					return 2;
				}
			}
		} catch (FilesNotFoundException $e) {
		}

		return 0;
	}

	/**
	 * @param string $userId
	 * @param Folder $folder
	 *
	 * @return array
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function recurseFolder(string $userId, Folder $folder): array {
		// Find index page or create it if we have subpages, but it doesn't exist
		try {
			$indexPage = $this->getPageByFile($this->getIndexPageFile($folder));
		} catch (NotFoundException $e) {
			if (!self::folderHasSubPages($folder)) {
				return [];
			}
			$indexPage = $this->newPage($userId, $folder, PageInfo::INDEX_PAGE_TITLE);
		}
		$pageInfos = [$indexPage];

		// Add subpages and recurse over subfolders
		try {
			foreach ($folder->getDirectoryListing() as $node) {
				if ($node instanceof File && self::isPage($node) && !self::isIndexPage($node)) {
					$pageInfos[] = $this->getPageByFile($node);
				} elseif ($node instanceof Folder) {
					array_push($pageInfos, ...$this->recurseFolder($userId, $node));
				}
			}
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		return $pageInfos;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 *
	 * @return PageInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findAll(string $userId, Collective $collective): array {
		$folder = $this->getCollectiveFolder($userId, $collective);
		try {
			return $this->recurseFolder($userId, $folder);
		} catch (NotPermittedException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param string     $search
	 *
	 * @return PageInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function findByString(string $userId, Collective $collective, string $search): array {
		$allPages = $this->findAll($userId, $collective);
		$pageInfos = [];
		foreach ($allPages as $page) {
			if (stripos($page->getTitle(), $search) === false) {
				continue;
			}
			$pageInfos[] = $page;
		}

		return $pageInfos;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function find(string $userId, Collective $collective, int $parentId, int $id): PageInfo {
		$folder = $this->getFolder($userId, $collective, $parentId);
		return $this->getPageByFile($this->nodeHelper->getFileById($folder, $id));
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param string     $title
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function create(string $userId, Collective $collective, int $parentId, string $title): PageInfo {
		$folder = $this->getFolder($userId, $collective, $parentId);
		$parentFile = $this->nodeHelper->getFileById($folder, $parentId);
		$folder = $this->initSubFolder($parentFile);
		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		$filename = NodeHelper::generateFilename($folder, $safeTitle, PageInfo::SUFFIX);

		return $this->newPage($userId, $folder, $filename);
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function touch(string $userId, Collective $collective, int $parentId, int $id): PageInfo {
		$folder = $this->getFolder($userId, $collective, $parentId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);
		$pageInfo->setLastUserId($userId);
		$this->updatePage($userId, $pageInfo->getId());
		return $pageInfo;
	}

	/**
	 * @param Folder $collectiveFolder
	 * @param int    $parentId
	 * @param File   $file
	 * @param string $title
	 *
	 * @return bool
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function renamePage(Folder $collectiveFolder, int $parentId, File $file, string $title): bool {
		$moveFolder = false;
		if ($parentId !== $this->getParentPageId($file)) {
			$newFolder = $this->initSubFolder($this->nodeHelper->getFileById($collectiveFolder, $parentId));
			$moveFolder = true;
		} else {
			$newFolder = $this->nodeHelper->getFileById($collectiveFolder, $parentId)->getParent();
		}

		$safeTitle = $this->nodeHelper->sanitiseFilename($title, self::DEFAULT_PAGE_TITLE);
		// If processing an index page, then rename the parent folder, otherwise the file itself
		$node = self::isIndexPage($file) ? $file->getParent() : $file;
		$suffix = self::isIndexPage($file) ? '' : PageInfo::SUFFIX;
		$newSafeName = $safeTitle . $suffix;

		// Neither path nor title changed, nothing to do
		if (!$moveFolder && $newSafeName === $node->getName()) {
			return false;
		}

		$newFileName = NodeHelper::generateFilename($newFolder, $safeTitle, PageInfo::SUFFIX);
		try {
			$node->move($newFolder->getPath() . '/' . $newFileName . $suffix);
		} catch (InvalidPathException | FilesNotFoundException | LockedException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		return true;
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 * @param string     $title
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function rename(string $userId, Collective $collective, int $parentId, int $id, string $title): PageInfo {
		$collectiveFolder = $this->getCollectiveFolder($userId, $collective);
		$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		if ($this->renamePage($collectiveFolder, $parentId, $file, $title)) {
			// Refresh the file after it has been renamed
			$file = $this->nodeHelper->getFileById($collectiveFolder, $id);
		}
		try {
			$this->updatePage($userId, $file->getId());
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		}

		$this->revertSubFolders($collectiveFolder);
		return $this->getPageByFile($file);
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageInfo
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function delete(string $userId, Collective $collective, int $parentId, int $id): PageInfo {
		$folder = $this->getFolder($userId, $collective, $parentId);
		$file = $this->nodeHelper->getFileById($folder, $id);
		$pageInfo = $this->getPageByFile($file);

		try {
			if (self::isIndexPage($file)) {
				// Don't delete if still page has subpages
				if ($this->pageHasOtherContent($file)) {
					throw new NotPermittedException('Failed to delete page ' . $id . ' with subpages');
				}

				// Delete folder if it's an index page without subpages
				$file->getParent()->delete();
			} else {
				// Delete file if it's not an index page
				$file->delete();
			}
		} catch (InvalidPathException | FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
		$this->pageMapper->deleteByFileId($pageInfo->getId());

		$this->revertSubFolders($folder);
		return $pageInfo;
	}


	/**
	 * @param string   $collectiveName
	 * @param PageInfo $page
	 * @param bool     $withFileId
	 *
	 * @return string
	 */
	public function getPageLink(string $collectiveName, PageInfo $page, bool $withFileId = true): string {
		$collectiveRoute = rawurlencode($collectiveName);
		$pagePathRoute = implode('/', array_map('rawurlencode', explode('/', $page->getFilePath())));
		$pageTitleRoute = rawurlencode($page->getTitle());
		$fullRoute = implode('/', array_filter([
			$collectiveRoute,
			$pagePathRoute,
			$pageTitleRoute
		]));

		return $withFileId ? $fullRoute . '?fileId=' . $page->getId() : $fullRoute;
	}

	/**
	 * @param PageInfo $page
	 * @param string   $content
	 *
	 * @return bool
	 */
	public function matchBacklinks(PageInfo $page, string $content): bool {
		$prefix = '/\[[^\]]+\]\(';
		$suffix = '\)/';

		$protocol = 'https?:\/\/';
		$trustedDomainConfig = (array)$this->config->getSystemValue('trusted_domains', []);
		$trustedDomains = !empty($trustedDomainConfig) ? '(' . implode('|', $trustedDomainConfig) . ')' : 'localhost';

		$basePath = str_replace('/', '/+', str_replace('/', '/+', preg_quote(trim(\OC::$WEBROOT, '/'), '/'))) . '(\/+index\.php)?';

		$relativeUrl = '(?!' . $protocol . '[^\/]+)';
		$absoluteUrl = $protocol . $trustedDomains . '(:[0-9]+)?';

		$appPath = '\/+apps\/+collectives\/+';

		$pagePath = str_replace('/', '/+', preg_quote($this->getPageLink(explode('/', $page->getCollectivePath())[1], $page, false), '/'));
		$fileId = '.+\?fileId=' . $page->getId();

		$relativeFileIdPattern = $prefix . $relativeUrl . $fileId . $suffix;
		$absoluteFileIdPattern = $prefix . $absoluteUrl . $basePath . $appPath . $fileId . $suffix;

		$relativePathPattern = $prefix . $relativeUrl . $basePath . $appPath . $pagePath . $suffix;
		$absolutePathPattern = $prefix . $absoluteUrl . $basePath . $appPath . $pagePath . $suffix;

		return preg_match($relativeFileIdPattern, $content, $linkMatches) ||
			preg_match($relativePathPattern, $content, $linkMatches) ||
			preg_match($absoluteFileIdPattern, $content, $linkMatches) ||
			preg_match($absolutePathPattern, $content, $linkMatches);
	}

	/**
	 * @param string     $userId
	 * @param Collective $collective
	 * @param int        $parentId
	 * @param int        $id
	 *
	 * @return PageInfo[]
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getBacklinks(string $userId, Collective $collective, int $parentId, int $id): array {
		$page = $this->find($userId, $collective, $parentId, $id);
		$allPages = $this->findAll($userId, $collective);

		$backlinks = [];
		foreach ($allPages as $p) {
			$file = $this->nodeHelper->getFileById($this->getFolder($userId, $collective, $p->getId()), $p->getId());
			$content = NodeHelper::getContent($file);
			if ($this->matchBacklinks($page, $content)) {
				$backlinks[] = $p;
			}
		}

		return $backlinks;
	}
}
