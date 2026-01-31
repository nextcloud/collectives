<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Fs\NodeHelper;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\Files\NotPermittedException as FilesNotPermittedException;
use OCP\IPreview;

class AttachmentService {
	public function __construct(
		private IPreview $preview,
	) {
	}

	/**
	 * @throws NotFoundException
	 */
	private function fileToInfo(File $file, folder $folder, string $type = 'text'): array {
		try {
			return [
				'id' => $file->getId(),
				'name' => $file->getName(),
				'filesize' => $file->getSize(),
				'mimetype' => $file->getMimeType(),
				'timestamp' => $file->getMTime(),
				'path' => $folder->getRelativePath($file->getPath()),
				'internalPath' => $file->getInternalPath(),
				'hasPreview' => $this->preview->isAvailable($file),
				'type' => $type,
			];
		} catch (FilesNotFoundException|InvalidPathException $e) {
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
		} catch (FilesNotFoundException|InvalidPathException) {
			throw new NotFoundException('Failed to get attachment directory for page ' . $pageFile->getId() . '.');
		}
		throw new NotFoundException('Failed to get attachment directory for page ' . $pageFile->getId() . '.');
	}

	private function getTextAttachments(File $pageFile, Folder $folder): array {
		try {
			$attachmentDir = $this->getAttachmentDirectory($pageFile);
		} catch (NotFoundException) {
			// No attachment folder -> empty list
			// return [];
		}

		// Only return files, ignore folders
		return array_map(fn ($file) => $this->fileToInfo($file, $folder, 'text'), array_filter($attachmentDir->getDirectoryListing(), static fn ($node) => $node instanceof File));
	}

	private function getFolderAttachments(File $pageFile, Folder $folder): array {
		if (!NodeHelper::isIndexPage($pageFile)) {
			return [];
		}

		$attachmentDir = $pageFile->getParent();
		// Only return files that are not pages
		return array_map(fn ($file) => $this->fileToInfo($file, $folder, 'folder'), array_filter($attachmentDir->getDirectoryListing(), static fn ($node) => $node instanceof File && !NodeHelper::isPage($node)));
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getAttachments(File $pageFile, Folder $folder): array {
		return array_merge($this->getTextAttachments($pageFile, $folder), $this->getFolderAttachments($pageFile, $folder));
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function renameAttachment(File $pageFile, int $attachmentId, string $targetName): array {
		$attachmentFolder = $this->getAttachmentDirectory($pageFile);
		$node = $attachmentFolder->getById($attachmentId);
		if (count($node) === 0) {
			throw new NotFoundException('Attachment not found: ' . $attachmentId . '.');
		}
		$node = $node[0];
		if (!($node instanceof File)) {
			throw new NotFoundException('Attachment not a file: ' . $attachmentId . '.');
		}
		try {
			$newNode = $node->move($attachmentFolder->getPath() . DIRECTORY_SEPARATOR . $targetName);
		} catch (FilesNotFoundException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}

		if (!($newNode instanceof File)) {
			throw new NotFoundException('Node not a file: ' . $newNode->getId());
		}

		return $this->fileToInfo($newNode, $attachmentFolder);
	}

	/**
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function deleteAttachment(File $pageFile, int $attachmentId): void {
		$attachmentFolder = $this->getAttachmentDirectory($pageFile);
		$node = $attachmentFolder->getById($attachmentId);
		if (count($node) === 0) {
			throw new NotFoundException('Attachment not found: ' . $attachmentId . '.');
		}
		try {
			$node[0]->delete();
		} catch (FilesNotFoundException|InvalidPathException $e) {
			throw new NotFoundException($e->getMessage());
		} catch (FilesNotPermittedException $e) {
			throw new NotPermittedException($e->getMessage());
		}
	}
}
