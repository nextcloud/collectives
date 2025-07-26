<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\NotFoundException as FilesNotFoundException;
use OCP\IPreview;

class AttachmentService {
	public function __construct(
		private IPreview $preview,
	) {
	}

	/**
	 * @throws NotFoundException
	 */
	private function fileToInfo(File $file, folder $folder): array {
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

	/**
	 * @param File $pageFile file of the page with the attachments.
	 * @param Folder $folder user or share folder for relative paths.
	 *
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function getAttachments(File $pageFile, Folder $folder): array {
		try {
			$attachmentDir = $this->getAttachmentDirectory($pageFile);
		} catch (NotFoundException) {
			// No attachment folder -> empty list
			return [];
		}

		// Only return files, ignore folders
		return array_map(fn ($file) => $this->fileToInfo($file, $folder), array_filter($attachmentDir->getDirectoryListing(), static fn ($node) => $node instanceof File));
	}
}
