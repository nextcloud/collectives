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
use OCP\IPreview;

class AttachmentService {
	private array $attachmentDirectory = [];

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
	private function getAttachmentDirectory(File $pageFile, bool $createIfNotExists = false): Folder {
		$id = $pageFile->getId();
		if (!isset($this->attachmentDirectory[$id])) {
			try {
				$parentFolder = $pageFile->getParent();
				$attachmentFolderName = '.attachments.' . $id;
				if ($parentFolder->nodeExists($attachmentFolderName)) {
					$attachmentFolder = $parentFolder->get($attachmentFolderName);
					if ($attachmentFolder instanceof Folder) {
						$this->attachmentDirectory[$id] = $attachmentFolder;
					}
				} elseif ($createIfNotExists) {
					$this->attachmentDirectory[$id] = $parentFolder->newFolder($attachmentFolderName);
				}
			} catch (FilesNotFoundException|InvalidPathException) {
				throw new NotFoundException('Failed to get attachment directory for page ' . $id . '.');
			}

			if (!isset($this->attachmentDirectory[$id])) {
				throw new NotFoundException('Attachment directory for page ' . $id . ' does not exist.');
			}
		}

		return $this->attachmentDirectory[$id];
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

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function putAttachment(File $pageFile, string $attachmentName, string $content): string {
		$attachmentDir = $this->getAttachmentDirectory($pageFile, true);

		$filename = NodeHelper::generateFilename($attachmentDir, $attachmentName);
		$attachmentDir->newFile($filename, $content);
		return '.attachments.' . $pageFile->getId() . DIRECTORY_SEPARATOR . $filename;
	}
}
