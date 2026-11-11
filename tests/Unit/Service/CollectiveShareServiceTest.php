<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OC\Share20\Share;
use OCA\Circles\Model\Member;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveShare;
use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Fs\UserFolderHelper;
use OCA\Collectives\Service\CollectiveShareService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\UnprocessableEntityException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\Folder;
use OCP\IDateTimeZone;
use OCP\IL10N;
use OCP\ISession;
use OCP\Lock\LockedException;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class CollectiveShareServiceTest extends TestCase {
	private IShareManager $shareManager;
	private UserFolderHelper $userFolderHelper;
	private CollectiveShareMapper $collectiveShareMapper;
	private CollectiveShareService $service;
	private Collective $collective;

	private string $userId = 'jane';
	private int $collectiveId = 123;
	private string $collectiveName = 'Test Collective';
	private Folder $collectiveFolder;
	private \DateTimeZone $userTimeZone;

	protected function setUp(): void {
		parent::setUp();
		$this->shareManager = $this->getMockBuilder(IShareManager::class)
			->disableOriginalConstructor()
			->getMock();

		$this->userFolderHelper = $this->getMockBuilder(UserFolderHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->collectiveShareMapper = $this->getMockBuilder(CollectiveShareMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$l10n->method('t')->willReturnArgument(0);

		$collectiveMapper = $this->createMock(CollectiveMapper::class);
		$pageService = $this->createMock(PageService::class);
		$session = $this->createMock(ISession::class);
		$this->userTimeZone = new \DateTimeZone('Europe/Berlin');
		$dateTimeZone = $this->createMock(IDateTimeZone::class);
		$dateTimeZone->method('getTimeZone')->willReturnCallback(fn (): \DateTimeZone => $this->userTimeZone);

		$this->service = new CollectiveShareService(
			$this->shareManager,
			$this->userFolderHelper,
			$this->collectiveShareMapper,
			$collectiveMapper,
			$pageService,
			$l10n,
			$session,
			$dateTimeZone,
		);

		$this->collective = new Collective();
		$this->collective->setId($this->collectiveId);
		$this->collective->setName($this->collectiveName);
	}

	private function prepareFolderShare(): Share&MockObject {
		$share = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('newShare')
			->willReturn($share);

		$this->shareManager->method('shareApiAllowLinks')
			->willReturn(true);

		$userFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$this->collectiveFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$userFolder->method('get')
			->willReturnMap([
				[$this->collectiveName, $this->collectiveFolder]
			]);
		$this->userFolderHelper->method('get')
			->willReturnMap([
				[$this->userId, $userFolder]
			]);

		$share->method('getNode')
			->willReturn($this->collectiveFolder);

		return $share;
	}

	public function testCreateFolderShareLockedException(): void {
		$this->prepareFolderShare();
		$this->collectiveFolder->method('lock')
			->willThrowException(new LockedException(''));

		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessage('Could not create share');
		$this->service->createFolderShare($this->userId, $this->collectiveName, 0);
	}

	public function testCreateFolderShareGenericShareException(): void {
		$this->prepareFolderShare();
		$this->collectiveFolder
			->expects(self::once())
			->method('unlock');

		$this->shareManager->method('createShare')
			->willThrowException(new GenericShareException(''));

		$this->expectException(NotFoundException::class);
		$this->service->createFolderShare($this->userId, $this->collectiveName, 0);
	}

	public function testCreateFolderShare(): void {
		$this->prepareFolderShare();
		$this->collectiveFolder
			->expects(self::once())
			->method('unlock');

		$share = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$share->method('getNode')
			->willReturn($this->collectiveFolder);
		$this->shareManager->method('createShare')
			->willReturn($share);

		self::assertEquals($share, $this->service->createFolderShare($this->userId, $this->collectiveName, 0));
	}

	public function testCreateFolderShareWithoutLabel(): void {
		$newShare = $this->prepareFolderShare();
		$newShare->expects(self::once())
			->method('setLabel')
			->with('');
		$this->shareManager->method('createShare')
			->willReturnArgument(0);

		$this->service->createFolderShare($this->userId, $this->collectiveName, 0);
	}

	private function prepareCreateShare(string $label = ''): Share&MockObject {
		$newShare = $this->prepareFolderShare();

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$folderShare->method('getNode')
			->willReturn($this->collectiveFolder);
		$folderShare->method('getToken')
			->willReturn('token');
		$folderShare->method('getLabel')
			->willReturn($label);
		$this->shareManager->method('createShare')
			->willReturn($folderShare);

		$this->collectiveShareMapper->method('create')
			->willReturn(new CollectiveShare());

		return $newShare;
	}

	public function testCreateShareWithLabel(): void {
		$newShare = $this->prepareCreateShare('Newsletter');
		$newShare->expects(self::once())
			->method('setLabel')
			->with('Newsletter');

		$share = $this->service->createShare($this->userId, $this->collective, null, null, '  Newsletter ');
		self::assertEquals('Newsletter', $share->jsonSerialize()['label']);
	}

	public function testCreateShareLabelTooLong(): void {
		$this->shareManager->expects(self::never())
			->method('createShare');

		$this->expectException(UnprocessableEntityException::class);
		$this->service->createShare($this->userId, $this->collective, null, null, str_repeat('a', 256));
	}

	public function testFindShareDoesNotExistException(): void {
		// Return null when collective share is not found
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willThrowException(new DoesNotExistException(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testFindShareMultipleObjectsReturnedException(): void {
		// Return null when more than one collective shares found
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willThrowException(new MultipleObjectsReturnedException(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testFindShareShareNotFoundException(): void {
		$collectiveShare = new CollectiveShare();
		$collectiveShare->setCollectiveId($this->collectiveId);
		$collectiveShare->setOwner($this->userId);
		$collectiveShare->setToken('token');
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);

		// Return null when folder share is not found
		$this->shareManager->method('getShareByToken')
			->willThrowException(new ShareNotFound(''));

		self::assertNull($this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testFindShare(): void {
		$collectiveShare = new CollectiveShare();
		$collectiveShare->setCollectiveId($this->collectiveId);
		$collectiveShare->setOwner($this->userId);
		$collectiveShare->setToken('token');
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndUser')
			->willReturn($collectiveShare);
		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		self::assertEquals($collectiveShare, $this->service->findShare($this->userId, $this->collectiveId, 0));

		// Test with share write permissions
		$folderShare->method('getPermissions')
			->willReturn(15);

		$collectiveShare->setEditable(true);
		self::assertEquals($collectiveShare, $this->service->findShare($this->userId, $this->collectiveId, 0));
	}

	public function testUpdateShare(): void {
		$collectiveShare = new CollectiveShare();
		$collectiveShare->setCollectiveId($this->collectiveId);
		$collectiveShare->setOwner($this->userId);
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndTokenAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		// Without share write permissions
		self::assertEquals($collectiveShare, $this->service->updateShare($this->userId, $this->collective, null, 'token', false, ''));

		// With share write permissions
		$permissions = 15;
		$folderShare->method('getPermissions')
			->willReturn($permissions);
		$collectiveShare->setEditable(true);
		self::assertEquals($collectiveShare, $this->service->updateShare($this->userId, $this->collective, null, 'token', true, ''));
	}

	public function testUpdateShareEditableWithRestrictedEditing(): void {
		$this->collective->setPermissions(Collective::defaultPermissions);
		$this->collective->updatePermissionLevel(Member::LEVEL_ADMIN, Collective::editPermissions);
		$this->collective->setLevel(Member::LEVEL_ADMIN);

		$this->shareManager->expects(self::never())
			->method('updateShare');

		$this->expectException(NotPermittedException::class);
		$this->service->updateShare($this->userId, $this->collective, null, 'token', true);
	}

	public function testUpdateShareWithPassword(): void {
		$collectiveShare = new CollectiveShare();
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndTokenAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		$folderShare->method('getPassword')
			->willReturn('passwordhash');
		$collectiveShare = $this->service->updateShare($this->userId, $this->collective, null, 'token', false, 'password');
		self::assertEquals('passwordhash', $collectiveShare->getPassword());
	}

	/**
	 * Expiration date as returned by the share provider: end of the day in
	 * the user's timezone, converted to the server's default timezone (UTC)
	 */
	private function providerExpirationDate(string $date): \DateTime {
		return (new \DateTime($date . ' 23:59:59', $this->userTimeZone))
			->setTimezone(new \DateTimeZone('UTC'));
	}

	public static function timeZoneProvider(): array {
		return [
			['UTC'],
			['Europe/Berlin'],
			['America/New_York'],
			['Pacific/Honolulu'],
			['Pacific/Kiritimati'],
		];
	}

	private function prepareUpdateShare(?\DateTime $currentExpirationDate = null): Share&MockObject {
		$collectiveShare = new CollectiveShare();
		$collectiveShare->setCollectiveId($this->collectiveId);
		$collectiveShare->setOwner($this->userId);
		$this->collectiveShareMapper->method('findOneByCollectiveIdAndTokenAndUser')
			->willReturn($collectiveShare);

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$folderShare->method('getExpirationDate')
			->willReturn($currentExpirationDate);
		$this->shareManager->method('getShareByToken')
			->willReturn($folderShare);

		return $folderShare;
	}

	public function testUpdateShareSetsLabel(): void {
		$folderShare = $this->prepareUpdateShare();
		$folderShare->expects(self::once())
			->method('setLabel')
			->with('Newsletter');
		$folderShare->method('getLabel')
			->willReturn('Newsletter');
		$this->shareManager->expects(self::once())
			->method('updateShare');

		$share = $this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, null, ' Newsletter  ');
		self::assertEquals('Newsletter', $share->jsonSerialize()['label']);
	}

	public function testUpdateShareRemovesLabel(): void {
		$folderShare = $this->prepareUpdateShare();
		$folderShare->expects(self::once())
			->method('setLabel')
			->with('');

		$this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, null, '');
	}

	public function testUpdateShareKeepsLabel(): void {
		$folderShare = $this->prepareUpdateShare();
		$folderShare->expects(self::never())
			->method('setLabel');
		$folderShare->method('getLabel')
			->willReturn('Newsletter');

		$share = $this->service->updateShare($this->userId, $this->collective, null, 'token', false);
		self::assertEquals('Newsletter', $share->jsonSerialize()['label']);
	}

	public function testUpdateShareLabelTooLong(): void {
		$folderShare = $this->prepareUpdateShare();
		$folderShare->expects(self::never())
			->method('setLabel');
		$this->shareManager->expects(self::never())
			->method('updateShare');

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Label must not be longer than 255 characters');
		$this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, null, str_repeat('a', 256));
	}

	public function testUpdateShareSetsExpirationDate(): void {
		$folderShare = $this->prepareUpdateShare();
		$folderShare->expects(self::once())
			->method('setExpirationDate')
			->with(self::callback(static fn (?\DateTime $date): bool => $date?->format('Y-m-d H:i:s') === '2026-12-24 00:00:00'
			&& $date->getTimezone()->getName() === 'Europe/Berlin'));
		$this->shareManager->expects(self::once())
			->method('updateShare');

		$this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, '2026-12-24');
	}

	public function testUpdateShareRemovesExpirationDate(): void {
		$folderShare = $this->prepareUpdateShare($this->providerExpirationDate('2026-12-24'));
		$folderShare->expects(self::once())
			->method('setExpirationDate')
			->with(null);

		$this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, '');
	}

	/**
	 * @dataProvider timeZoneProvider
	 */
	public function testUpdateShareKeepsExpirationDate(string $timeZone): void {
		$this->userTimeZone = new \DateTimeZone($timeZone);
		$providerExpirationDate = $this->providerExpirationDate('2026-12-24');
		$folderShare = $this->prepareUpdateShare($providerExpirationDate);
		$folderShare->expects(self::never())
			->method('setExpirationDate');

		$share = $this->service->updateShare($this->userId, $this->collective, null, 'token', false);
		self::assertEquals('2026-12-24', $share->jsonSerialize()['expirationDate']);
		// The date of the folder share is not modified
		self::assertEquals('UTC', $providerExpirationDate->getTimezone()->getName());
	}

	public static function invalidExpirationDateProvider(): array {
		return [
			['tomorrow'],
			['24.12.2026'],
			['2026-12'],
			['2026-12-24 12:00'],
		];
	}

	/**
	 * @dataProvider invalidExpirationDateProvider
	 */
	public function testUpdateShareInvalidExpirationDate(string $expirationDate): void {
		$folderShare = $this->prepareUpdateShare();
		$folderShare->expects(self::never())
			->method('setExpirationDate');
		$this->shareManager->expects(self::never())
			->method('updateShare');

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Invalid date. Format must be YYYY-MM-DD');
		$this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, $expirationDate);
	}

	public function testUpdateShareExpirationDateEnforced(): void {
		$this->prepareUpdateShare($this->providerExpirationDate('2026-12-24'));
		$this->shareManager->method('updateShare')
			->willThrowException(new \InvalidArgumentException('Expiration date is enforced'));

		$this->expectException(UnprocessableEntityException::class);
		$this->expectExceptionMessage('Expiration date is enforced');
		$this->service->updateShare($this->userId, $this->collective, null, 'token', false, null, '');
	}

	/**
	 * @dataProvider timeZoneProvider
	 */
	public function testCreateShareReturnsExpirationDate(string $timeZone): void {
		$this->userTimeZone = new \DateTimeZone($timeZone);
		$this->prepareFolderShare();

		$folderShare = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$folderShare->method('getNode')
			->willReturn($this->collectiveFolder);
		$folderShare->method('getToken')
			->willReturn('token');
		$folderShare->method('getExpirationDate')
			->willReturn($this->providerExpirationDate('2026-12-24'));
		$this->shareManager->method('createShare')
			->willReturn($folderShare);

		$collectiveShare = new CollectiveShare();
		$this->collectiveShareMapper->method('create')
			->willReturn($collectiveShare);

		$share = $this->service->createShare($this->userId, $this->collective, null);
		self::assertEquals('2026-12-24', $share->jsonSerialize()['expirationDate']);
	}

	public function testIsShareNotAuthenticated(): void {
		$share = $this->getMockBuilder(Share::class)
			->disableOriginalConstructor()
			->getMock();
		$share->method('getPassword')->willReturn('passwordhash');
		$this->shareManager->method('getShareByToken')->willReturn($share);

		self::assertFalse($this->service->isShareAuthenticated('token'));
	}
}
