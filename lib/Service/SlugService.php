<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Service;

use Symfony\Component\String\Slugger\SluggerInterface;

class SlugService {
	public function __construct(
		private SluggerInterface $slugger,
	) {
	}

	public function generateCollectiveSlug(string $name): string {
		return $this->slugger->slug($name)->toString();
	}

	public function generatePageSlug(string $title): string {
		return $this->slugger->slug($title)->toString();
	}
}
