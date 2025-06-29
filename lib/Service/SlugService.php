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

	public function generateSlug(string $string): string {
		return $this->slugger->slug($string)->toString();
	}
}
