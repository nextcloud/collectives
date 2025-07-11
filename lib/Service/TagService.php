<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\Tag;
use OCA\Collectives\Db\TagMapper;

class TagService {
	public function __construct(
		private TagMapper $tagMapper,
		private CollectiveMapper $collectiveMapper,
		private CollectiveServiceBase $collectiveService,
	) {
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws MissingDependencyException
	 */
	private function checkPermissions(int $collectiveId, string $userId, bool $edit = false): void {
		if ($this->collectiveMapper->findByIdAndUser($collectiveId, $userId) === null) {
			throw new NotFoundException('Collective not found: ' . $collectiveId);
		}

		if ($edit && !$this->collectiveService->getCollective($collectiveId, $userId)->canEdit()) {
			throw new NotPermittedException('Not allowed to edit collective');
		}
	}

	/**
	 * @throws NotFoundException
	 */
	public function index(int $collectiveId, string $userId): array {
		$this->checkPermissions($collectiveId, $userId);
		return $this->tagMapper->findAll($collectiveId);
	}

	/**
	 * @throws NotFoundException
	 * @throws TagExistsException
	 */
	public function create(int $collectiveId, string $userId, string $name, string $color): Tag {
		$this->checkPermissions($collectiveId, $userId, true);
		if ($this->tagMapper->findByName($collectiveId, $name) !== null) {
			throw new TagExistsException('Tag already exists for collective: ' . $name);
		}

		$tag = new Tag();
		$tag->setCollectiveId($collectiveId);
		$tag->setName($name);
		$tag->setColor($color);
		return $this->tagMapper->insert($tag);
	}

	/**
	 * @throws NotFoundException
	 */
	public function update(int $collectiveId, string $userId, int $id, string $name, string $color): Tag {
		$this->checkPermissions($collectiveId, $userId, true);
		$tag = $this->tagMapper->find($collectiveId, $id);
		$tag->setName($name);
		$tag->setColor($color);
		return $this->tagMapper->update($tag);
	}

	/**
	 * @throws NotFoundException
	 */
	public function delete(int $collectiveId, string $userId, int $id): void {
		$this->checkPermissions($collectiveId, $userId, true);
		$tag = $this->tagMapper->find($collectiveId, $id);
		$this->tagMapper->delete($tag);
	}
}
