<?php

namespace OCA\Collectives\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IPreview;

class AttachmentService {
	private PageService $pageService;
	private IPreview $preview;

	public function __construct(PageService $pageService,
								IPreview $preview) {
		$this->pageService = $pageService;
		$this->preview = $preview;
	}

	/**
	 * @param File   $file
	 * @param string $userId
	 *
	 * @return array
	 * @throws NotFoundException
	 */
	private function fileToInfo(File $file, string $userId): array {
		try {
			return [
				'id' => $file->getId(),
				'name' => $file->getName(),
				'filesize' => $file->getSize(),
				'mimetype' => $file->getMimeType(),
				'timestamp' => $file->getMTime(),
				'path' => substr($file->getPath(), strlen('/' . $userId . '/files')),
				'internalPath' => $file->getInternalPath(),
				'hasPreview' => $this->preview->isAvailable($file),
			];
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		}
	}

	/**
	 * @param File $pageFile
	 *
	 * @return Folder
	 * @throws NotFoundException
	 */
	private function getAttachmentDirectory(File $pageFile): Folder {
		try {
			$parentFolder = $pageFile->getParent();
			$attachmentFolderName = '.attachments.' . $pageFile->getId();
			if ($parentFolder->nodeExists($attachmentFolderName)) {
				$attachmentFolder = $parentFolder->get($attachmentFolderName);
				if ($attachmentFolder instanceof Folder) {
					return $attachmentFolder;
				}
			}
		} catch (FilesNotFoundException | InvalidPathException $e) {
			throw new NotFoundException('Failed to get attachment directory for page ' . $pageFile->getId() . '.');
		}
		throw new NotFoundException('Failed to get attachment directory for page ' . $pageFile->getId() . '.');
	}

	/**
	 * @param int    $collectiveId
	 * @param int    $pageId
	 * @param string $userId
	 *
	 * @return array
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getAttachments(int $collectiveId, int $pageId, string $userId): array {
		$pageFile = $this->pageService->getPageFile($collectiveId, $pageId, $userId);
		try {
			$attachmentDir = $this->getAttachmentDirectory($pageFile);
		} catch (NotFoundException $e) {
			// No attachment folder -> empty list
			return [];
		}

		// Only return files, ignore folders
		return array_map(function ($file) use ($userId) {
			return $this->fileToInfo($file, $userId);
		}, array_filter($attachmentDir->getDirectoryListing(), static function ($node) {
			return $node instanceof File;
		}));
	}
}
