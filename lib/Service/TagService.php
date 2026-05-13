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
	 */
	private function checkReadPermissions(int $collectiveId, string $userId): void {
		if ($this->collectiveMapper->findByIdAndUser($collectiveId, $userId) === null) {
			throw new NotFoundException('Collective not found: ' . $collectiveId);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	private function checkEditPermissions(int $collectiveId, string $userId): void {
		$this->checkReadPermissions($collectiveId, $userId);

		if (!$this->collectiveService->getCollective($collectiveId, $userId)->canEdit()) {
			throw new NotPermittedException('Not allowed to edit collective');
		}
	}

	/**
	 * @throws UnprocessableEntityException
	 */
	private function validateColor(string $color): void {
		if ($color !== '' && !preg_match('/^[0-9a-fA-F]{6}$/', $color)) {
			throw new UnprocessableEntityException('Collective color is not valid: ' . $color);
		}
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function index(int $collectiveId, string $userId): array {
		$this->checkReadPermissions($collectiveId, $userId);
		return $this->tagMapper->findAll($collectiveId);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 * @throws TagExistsException
	 * @throws UnprocessableEntityException
	 */
	public function create(int $collectiveId, string $userId, string $name, string $color): Tag {
		$this->checkEditPermissions($collectiveId, $userId);
		$this->validateColor($color);
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
	 * @throws NotPermittedException
	 * @throws UnprocessableEntityException
	 */
	public function update(int $collectiveId, string $userId, int $id, string $name, string $color): Tag {
		$this->checkEditPermissions($collectiveId, $userId);
		$this->validateColor($color);
		$tag = $this->tagMapper->find($collectiveId, $id);
		$tag->setName($name);
		$tag->setColor($color);
		return $this->tagMapper->update($tag);
	}

	/**
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function delete(int $collectiveId, string $userId, int $id): void {
		$this->checkEditPermissions($collectiveId, $userId);
		$tag = $this->tagMapper->find($collectiveId, $id);
		$this->tagMapper->delete($tag);
	}
}
