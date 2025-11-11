<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Stemmer;

use OCP\IConfig;
use Wamania\Snowball\NotFoundException;
use Wamania\Snowball\Stemmer\Stemmer as WamaniaStemmer;
use Wamania\Snowball\StemmerFactory;

class Stemmer {
	private ?WamaniaStemmer $stemmer = null;
	private bool $stemmingEnabled = true;

	public function __construct(
		private IConfig $config,
	) {
	}

	public function stem(string $word): string {
		if ($this->stemmer === null && $this->stemmingEnabled) {
			$language = $this->config->getSystemValue('default_language', 'en');
			try {
				$this->stemmer = StemmerFactory::create($language);
			} catch (NotFoundException) {
				$this->stemmingEnabled = false;
				return $word;
			}
		}

		if (!$this->stemmingEnabled) {
			return $word;
		}

		try {
			return $this->stemmer->stem($word);
		} catch (\Exception) {
			return $word;
		}
	}
}
