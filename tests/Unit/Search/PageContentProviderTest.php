<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Service;

use OC\Files\Node\Folder;
use OC\Search\SearchQuery;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Search\PageContentProvider;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IFilterCollection;
use OCP\Search\ISearchQuery;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PageContentProviderTest extends TestCase {
	private PageContentProvider $provider;

	protected function setUp(): void {
		$collective = new Collective();
		$collective->setId(123);

		/** @var IL10N&MockObject $l10n */
		$l10n = $this->createMock(IL10N::class);
		/** @var IURLGenerator&MockObject $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var CollectiveHelper&MockObject $collectiveHelper */
		$collectiveHelper = $this->createMock(CollectiveHelper::class);
		$collectiveHelper->method('getCollectivesForUser')
			->willReturn([$collective]);
		$collectiveService = $this->createMock(CollectiveService::class);
		/** @var Folder&MockObject $folder */
		$folder = $this->createMock(Folder::class);
		$folder->method('getById')
			->willReturn([]);
		/** @var PageService&MockObject $pageService */
		$pageService = $this->createMock(PageService::class);
		$pageService->method('getCollectiveFolder')
			->willReturn($folder);
		/** @var SearchService&MockObject $indexedSearchService */
		$indexedSearchService = $this->createMock(SearchService::class);
		$indexedSearchService->method('searchCollective')
			->willReturn([404 => 'fileData']);
		/** @var LoggerInterface&MockObject $logger */
		$logger = $this->createMock(LoggerInterface::class);
		/** @var IAppManager&MockObject $appManager */
		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')
			->willReturn(true);

		$this->provider = new PageContentProvider(
			$l10n,
			$urlGenerator,
			$collectiveHelper,
			$collectiveService,
			$pageService,
			$indexedSearchService,
			$logger,
			$appManager
		);
	}

	public function testId(): void {
		$this->assertEquals('collectives-page-content', $this->provider->getId());
	}

	public function testSearchWithMissingFile(): void {
		/** @var IUser&MockObject $user */
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('jane');
		$filters = $this->createMock(IFilterCollection::class);
		$query = new SearchQuery(
			$filters,
			ISearchQuery::SORT_DATE_DESC,
			SearchQuery::LIMIT_DEFAULT,
			null,
			'collectives.'

		);
		$response = json_encode($this->provider->search($user, $query));
		$result = json_decode($response, true);

		$this->assertEquals(
			'[]',
			json_encode($result['entries'])
		);
	}
}
