<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IPreview;

class AttachmentService {
	public function __construct(private PageService $pageService, private IPreview $preview) {
	}

	/**
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
		} catch (FilesNotFoundException | InvalidPathException) {
			throw new NotFoundException('Failed to get attachment directory for page ' . $pageFile->getId() . '.');
		}
		throw new NotFoundException('Failed to get attachment directory for page ' . $pageFile->getId() . '.');
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getAttachments(int $collectiveId, int $pageId, string $userId): array {
		$pageFile = $this->pageService->getPageFile($collectiveId, $pageId, $userId);
		try {
			$attachmentDir = $this->getAttachmentDirectory($pageFile);
		} catch (NotFoundException) {
			// No attachment folder -> empty list
			return [];
		}

		// Only return files, ignore folders
		return array_map(fn ($file) => $this->fileToInfo($file, $userId), array_filter($attachmentDir->getDirectoryListing(), static fn ($node) => $node instanceof File));
	}
}
