<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit;

use OCA\Collectives\ConfigLexicon;
use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\Preset;
use OCP\IAppConfig;
use Test\TestCase;

class ConfigLexiconTest extends TestCase {
	private ConfigLexicon $lexicon;

	protected function setUp(): void {
		parent::setUp();
		$this->lexicon = new ConfigLexicon();
	}

	public function testAppConfigKeysAreUnique(): void {
		$keys = array_map(static fn (Entry $e) => $e->getKey(), $this->lexicon->getAppConfigs());
		self::assertSame(array_unique($keys), $keys);
	}

	public function testUserFolderHasNoLexiconDefault(): void {
		$entries = array_filter(
			$this->lexicon->getUserConfigs(),
			static fn (Entry $e) => $e->getKey() === ConfigLexicon::USER_FOLDER,
		);
		self::assertCount(1, $entries);
		self::assertNull(array_values($entries)[0]->getDefault(Preset::NONE));
	}

	public function testStaticSiteGeneratorTokenIsSensitive(): void {
		$entries = array_filter(
			$this->lexicon->getAppConfigs(),
			static fn (Entry $e) => $e->getKey() === ConfigLexicon::STATIC_SITE_GENERATOR_TOKEN,
		);
		self::assertCount(1, $entries);
		self::assertTrue(array_values($entries)[0]->isFlagged(IAppConfig::FLAG_SENSITIVE));
	}
}
