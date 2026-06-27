<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\PageLinkMapper;
use OCA\Collectives\Db\PageMapper;
use OCA\Collectives\Mount\CollectiveFolderManager;
use OCP\Files\Folder;
use OCP\IUserManager;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Combines the dependency-injected collaborators with the per-call arguments
 * required to build a PageInfoTreeBuilder.
 */
class PageInfoTreeBuilderFactory {
	public function __construct(
		private readonly PageMapper $pageMapper,
		private readonly PageLinkMapper $pageLinkMapper,
		private readonly IUserManager $userManager,
		private readonly SluggerInterface $slugger,
		private readonly CollectiveFolderManager $collectiveFolderManager,
		private readonly CollectiveServiceBase $collectiveService,
	) {
	}

	public function create(int $collectiveId, Folder $folder, string $userId, bool $recurse, bool $forceIndex): PageInfoTreeBuilder {
		return new PageInfoTreeBuilder(
			$this->pageMapper,
			$this->pageLinkMapper,
			$this->userManager,
			$this->slugger,
			$this->collectiveFolderManager,
			$this->collectiveService,
			$collectiveId,
			$folder,
			$userId,
			$recurse,
			$forceIndex,
		);
	}
}
