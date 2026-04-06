<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search\FileSearch\Stemmer;

use OCA\Collectives\Vendor\Wamania\Snowball\NotFoundException;
use OCA\Collectives\Vendor\Wamania\Snowball\Stemmer\Stemmer as WamaniaStemmer;
use OCA\Collectives\Vendor\Wamania\Snowball\StemmerFactory;
use OCP\IConfig;

class Stemmer {
	/** @var array<string, WamaniaStemmer|null> */
	private array $stemmers = [];

	public function __construct(
		private IConfig $config,
	) {
	}

	private function getStemmer(string $language): ?WamaniaStemmer {
		if (!array_key_exists($language, $this->stemmers)) {
			try {
				$this->stemmers[$language] = StemmerFactory::create($language);
			} catch (NotFoundException) {
				$this->stemmers[$language] = null;
			}
		}
		return $this->stemmers[$language];
	}

	public function stem(string $word, ?string $language = null): string {
		$language ??= $this->config->getSystemValue('default_language', 'en');
		$stemmer = $this->getStemmer($language);
		if ($stemmer === null) {
			return $word;
		}

		try {
			return $stemmer->stem($word);
		} catch (\Exception) {
			return $word;
		}
	}
}
