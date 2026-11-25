<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Db;

use DateTimeImmutable;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\Service\UnprocessableEntityException;
use Symfony\Component\Uid\UuidV7;
use Test\TestCase;
use UnexpectedValueException;

class StaticSiteTest extends TestCase {
	public function testEncodePageIdsReturnsJsonList(): void {
		$this->assertSame('[1,2,3]', StaticSite::encodePageIds([5 => 1, 7 => 2, 9 => 3]));
	}

	public function testEncodePageIdsThrowsForUnencodableValues(): void {
		$this->expectException(UnprocessableEntityException::class);
		StaticSite::encodePageIds([NAN]);
	}

	public static function inProgressProvider(): array {
		$now = 1800000000;
		return [
			'recent pending' => [StaticSite::STATUS_PENDING, $now - 60, $now, true],
			'pending at timeout' => [StaticSite::STATUS_PENDING, $now - StaticSite::PENDING_TIMEOUT, $now, true],
			'aborted pending' => [StaticSite::STATUS_PENDING, $now - StaticSite::PENDING_TIMEOUT - 1, $now, false],
			'provided' => [StaticSite::STATUS_PROVIDED, $now - 60, $now, true],
			'provided at timeout' => [StaticSite::STATUS_PROVIDED, $now - StaticSite::PENDING_TIMEOUT, $now, true],
			'aborted provided' => [StaticSite::STATUS_PROVIDED, $now - StaticSite::PENDING_TIMEOUT - 1, $now, false],
			'deleting' => [StaticSite::STATUS_DELETING, $now - 60, $now, true],
			'aborted deleting' => [StaticSite::STATUS_DELETING, $now - StaticSite::PENDING_TIMEOUT - 1, $now, false],
			'published' => [StaticSite::STATUS_PUBLISHED, $now - 60, $now, false],
			'failed' => [StaticSite::STATUS_FAILED, $now - 60, $now, false],
		];
	}

	/**
	 * @dataProvider inProgressProvider
	 */
	public function testIsInProgress(string $status, int $updatedAt, int $now, bool $expected): void {
		$staticSite = new StaticSite();
		$staticSite->setStatus($status);
		$staticSite->setUpdatedAt($updatedAt);

		$this->assertSame($expected, $staticSite->isInProgress($now));
	}

	public static function secondsUntilTimeoutProvider(): array {
		$now = 1800000000;
		return [
			'recent pending' => [StaticSite::STATUS_PENDING, $now - 60, $now, StaticSite::PENDING_TIMEOUT - 60],
			'deleting at timeout' => [StaticSite::STATUS_DELETING, $now - StaticSite::PENDING_TIMEOUT, $now, 0],
			'aborted provided' => [StaticSite::STATUS_PROVIDED, $now - StaticSite::PENDING_TIMEOUT - 1, $now, null],
			'published' => [StaticSite::STATUS_PUBLISHED, $now - 60, $now, null],
		];
	}

	/**
	 * @dataProvider secondsUntilTimeoutProvider
	 */
	public function testGetSecondsUntilTimeout(string $status, int $updatedAt, int $now, ?int $expected): void {
		$staticSite = new StaticSite();
		$staticSite->setStatus($status);
		$staticSite->setUpdatedAt($updatedAt);

		$this->assertSame($expected, $staticSite->getSecondsUntilTimeout($now));
	}

	public static function corruptedSelectedPagesProvider(): array {
		return [
			'invalid JSON' => ['[1,'],
			'scalar' => ['5'],
			'string' => ['"page"'],
			'null' => ['null'],
		];
	}

	/**
	 * @dataProvider corruptedSelectedPagesProvider
	 */
	public function testGetSelectedPageIdsThrowsForCorruptedValue(string $selectedPages): void {
		$staticSite = new StaticSite();
		$staticSite->setSelectedPages($selectedPages);

		$this->expectException(UnexpectedValueException::class);
		$staticSite->getSelectedPageIds();
	}

	public function testSelectedPageIdsRoundTrip(): void {
		$staticSite = new StaticSite();
		$staticSite->setSelectedPageIds([3, 1, 2]);

		$this->assertSame([3, 1, 2], $staticSite->getSelectedPageIds());
	}

	public function testJsonSerializeContainsLastErrorAndPublicationTime(): void {
		$staticSite = new StaticSite();
		$staticSite->setLastError(StaticSite::ERROR_GENERATOR_FAILED);
		$staticSite->setPublishedAt(1700000000);

		$json = $staticSite->jsonSerialize();

		$this->assertSame(StaticSite::ERROR_GENERATOR_FAILED, $json['lastError']);
		$this->assertSame(1700000000, $json['publishedAt']);
	}

	public function testJsonSerializeContainsPublishedBuild(): void {
		$staticSite = new StaticSite();
		$staticSite->setTitle('New Title');
		$staticSite->setSelectedPageIds([1, 2, 3]);
		$staticSite->setPublishedTitle('Title');
		$staticSite->setPublishedSelectedPages('[1,2]');
		$staticSite->setPublishedBuildId(UuidV7::generate(new DateTimeImmutable('@1700000000')));

		$json = $staticSite->jsonSerialize();

		$this->assertSame('Title', $json['publishedTitle']);
		$this->assertSame([1, 2], $json['publishedSelectedPageIds']);
		$this->assertSame(1700000000, $json['publishedBuildStartedAt']);
	}

	public function testJsonSerializeContainsNoPublishedBuildIfNeverPublished(): void {
		$json = (new StaticSite())->jsonSerialize();

		$this->assertNull($json['publishedTitle']);
		$this->assertNull($json['publishedSelectedPageIds']);
		$this->assertNull($json['publishedBuildStartedAt']);
	}

	public static function invalidBuildIdProvider(): array {
		return [
			'not a UUID' => ['invalid'],
			'UUIDv4 without time' => ['9b2f5c1e-8d3a-4f6b-9c7e-1a2b3c4d5e6f'],
		];
	}

	/**
	 * @dataProvider invalidBuildIdProvider
	 */
	public function testPublishedBuildStartedAtIsUnknownForInvalidBuildId(string $buildId): void {
		$staticSite = new StaticSite();
		$staticSite->setPublishedBuildId($buildId);

		$this->assertNull($staticSite->getPublishedBuildStartedAt());
	}

	public function testGetPublishedSelectedPageIdsThrowsForCorruptedValue(): void {
		$staticSite = new StaticSite();
		$staticSite->setPublishedSelectedPages('{invalid');

		$this->expectException(UnexpectedValueException::class);
		$staticSite->getPublishedSelectedPageIds();
	}

	public static function jsonInProgressProvider(): array {
		return [
			'recently provided' => [StaticSite::STATUS_PROVIDED, 60, true],
			'aborted provided' => [StaticSite::STATUS_PROVIDED, StaticSite::PENDING_TIMEOUT + 60, false],
			'published' => [StaticSite::STATUS_PUBLISHED, 60, false],
		];
	}

	/**
	 * @dataProvider jsonInProgressProvider
	 */
	public function testJsonSerializeEvaluatesInProgressWithServerTime(string $status, int $updatedAgo, bool $expected): void {
		$staticSite = new StaticSite();
		$staticSite->setStatus($status);
		$staticSite->setUpdatedAt(time() - $updatedAgo);

		$json = $staticSite->jsonSerialize();
		$this->assertSame($expected, $json['isInProgress']);
		$this->assertSame($expected, $json['secondsUntilTimeout'] !== null);
	}

	public function testJsonSerializeOmitsStaticSiteAndBuildIds(): void {
		$staticSite = new StaticSite();
		$staticSite->setStaticSiteId('01990000-0000-7000-8000-000000000000');
		$staticSite->setBuildId('01990000-0000-7000-8000-000000000001');
		$staticSite->setPublishedBuildId('01990000-0000-7000-8000-000000000002');

		$serialized = $staticSite->jsonSerialize();
		$this->assertArrayNotHasKey('staticSiteId', $serialized);
		$this->assertArrayNotHasKey('buildId', $serialized);
		$this->assertArrayNotHasKey('publishedBuildId', $serialized);
	}

	public function testIsPublished(): void {
		$staticSite = new StaticSite();
		$this->assertFalse($staticSite->isPublished());

		$staticSite->setPublishedUrl('https://example.org/my-site/');
		$this->assertTrue($staticSite->isPublished());
	}
}
