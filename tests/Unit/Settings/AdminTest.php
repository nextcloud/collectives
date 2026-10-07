<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Settings;

use OCA\Collectives\ConfigLexicon;
use OCA\Collectives\Settings\Admin;
use OCP\AppFramework\Services\IInitialState;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class AdminTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private array $initialState = [];
	private Admin $admin;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$initialState = $this->createMock(IInitialState::class);
		$initialState->method('provideInitialState')
			->willReturnCallback(function (string $key, mixed $data): void {
				$this->initialState[$key] = $data;
			});
		$this->admin = new Admin($this->appConfig, $initialState);
	}

	private function mockToken(string $token): void {
		$this->appConfig->method('getValueString')
			->willReturnCallback(static fn (string $app, string $key, string $default = ''): string => match ($key) {
				ConfigLexicon::STATIC_SITE_GENERATOR_TOKEN => $token,
				default => $default,
			});
	}

	public function testSavedTokenIsReplacedByPlaceholder(): void {
		$this->mockToken('secret-token');

		$this->admin->getForm();

		self::assertSame(Admin::TOKEN_PLACEHOLDER, $this->initialState['adminSettings']['static_site_generator_token']);
		self::assertStringNotContainsString('secret-token', json_encode($this->initialState));
	}

	public function testMissingTokenIsEmpty(): void {
		$this->mockToken('');

		$this->admin->getForm();

		self::assertSame('', $this->initialState['adminSettings']['static_site_generator_token']);
	}
}
