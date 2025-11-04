<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Fs;

use OC;
use OC\Files\Node\File;
use OC\Files\Node\Folder;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\NodeHelper;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\NotFoundException;
use OCP\IDBConnection;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NodeHelperTest extends TestCase {
	private IL10N $l10n;
	private NodeHelper $helper;

	protected function setUp(): void {
		parent::setUp();

		$db = $this->getMockBuilder(IDBConnection::class)
			->disableOriginalConstructor()
			->getMock();
		$this->l10n = $this->getMockBuilder(IL10N::class)
			->disableOriginalConstructor()
			->getMock();
		$logger = $this->getMockBuilder(LoggerInterface::class)
			->disableOriginalConstructor()
			->getMock();

		$this->helper = new NodeHelper($db, $this->l10n, $logger);
	}

	public function nameProvider(): array {
		return [
			['string with slash /', 'string with slash -'],
			['string with forbidden chars *|\\:"<>?', 'string with forbidden chars'],
			["string with forbidden UTF-8 chars #1: \xF0\x90\x80\x80", 'string with forbidden UTF-8 chars #1'],
			["string with forbidden UTF-8 chars #2: \xF0\xBF\xBF\xBF", 'string with forbidden UTF-8 chars #2'],
			["string with forbidden UTF-8 chars #3: \xF1\x80\x80\x80", 'string with forbidden UTF-8 chars #3'],
			["string with forbidden UTF-8 chars #3: \xF4\x80\x80\x80", 'string with forbidden UTF-8 chars #3'],
			["string with allowed UTF-8 chars #1: \x61\xC3\xB6\x61", "string with allowed UTF-8 chars #1 \x61\xC3\xB6\x61"],
			['string with spaces', 'string with spaces'],
			[' string with leading space', 'string with leading space'],
			['.string with leading dot', 'string with leading dot'],
			['string with trailing space ', 'string with trailing space'],
			['', 'New File']
		];
	}

	/**
	 * @dataProvider nameProvider
	 */
	public function testSanitiseFilename(string $input, string $output): void {
		$this->l10n->method('t')
			->willReturnArgument(0);

		self::assertEquals($output, $this->helper->sanitiseFilename($input));
		self::assertEquals('New Page', $this->helper->sanitiseFilename('', 'New Page'));
	}

	public function testGetFileById(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getById')
			->willReturnMap([
				[1, [$file]],
				[2, []],
			]);

		self::assertEquals($file, $this->helper->getFileById($folder, 1));

		$this->expectException(NotFoundException::class);
		$this->helper->getFileById($folder, 2);
	}

	public function filenameProvider(): array {
		return [
			['File exists1', 'File exists1 (2)'],
			['File exists2', 'File exists2 (4)'],
			['File exists2 (3)', 'File exists2 (4)'],
			['File exists3', 'File exists3 (2)'],
			['File exists4 (9)', 'File exists4 (10)'],
			['File exists5 (1i)', 'File exists5 (1i) (2)'],
			['File new', 'File new'],
			[' (2)', ' (3)'] ];
	}

	/**
	 * @dataProvider filenameProvider
	 */
	public function testGenerateFilename(string $input, string $output): void {
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('nodeExists')
			->willReturnMap([
				['File exists1', true],
				['File exists2', true],
				['File exists2 (2)', true],
				['File exists2 (3)', true],
				['File exists3', true],
				['File exists3 (1)', true],
				['File exists4 (9)', true],
				['File exists5 (1)', true],
				[' (2)', true],
				['File exists5 (1i)', true]
			]);

		self::assertEquals($output, NodeHelper::generateFilename($folder, $input));
	}

	public function testIsPageFilename(): void {
		self::assertTrue(NodeHelper::isPageFilename('page.md'));
		self::assertTrue(NodeHelper::isPageFilename('image.gz.md'));
		self::assertFalse(NodeHelper::isPageFilename('folder'));
		self::assertFalse(NodeHelper::isPageFilename('image.gz'));
	}

	public function testIsPage(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getName')
			->willReturnOnConsecutiveCalls(
				'page.md', 'image.gz'
			);

		self::assertTrue(NodeHelper::isPage($file));
		self::assertFalse(NodeHelper::isPage($file));
	}

	public function testIsIndexPage(): void {
		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getName')
			->willReturnOnConsecutiveCalls(
				'Readme.md', 'page.md'
			);
		self::assertTrue(NodeHelper::isIndexPage($file));
		self::assertFalse(NodeHelper::isIndexPage($file));
	}

	public function testHasSubPages(): void {
		$childFile1 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$childFile1->method('getName')
			->willReturn('Readme.md');

		$childFile2 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$childFile2->method('getName')
			->willReturn('File2.md');

		$childFile3 = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$childFile3->method('getName')
			->willReturn('File3.txt');

		$attachmentFolder = $this->createMock(Folder::class);
		$attachmentFolder->method('getName')
			->willReturn('.attachments.123');

		$children = [$childFile1, $childFile2, $childFile3, $attachmentFolder];
		$parentFolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();

		$file = $this->getMockBuilder(File::class)
			->disableOriginalConstructor()
			->getMock();
		$file->method('getParent')
			->willReturn($parentFolder);
		$file->method('getId')
			->willReturn('123');

		// Test `indexPageHasOtherContent()` with page in children
		$parentFolder->method('getDirectoryListing')
			->willReturnOnConsecutiveCalls(
				$children,
				[$attachmentFolder],
				[],
				$children,
				[$childFile1],
				$children,
				$children,
				$children
			);
		self::assertTrue(NodeHelper::indexPageHasOtherContent($file));
		// Test `indexPageHasOtherContent()` only with attachment folder
		self::assertFalse(NodeHelper::indexPageHasOtherContent($file));
		// Test `indexPageHasOtherContent()` without any children
		self::assertFalse(NodeHelper::indexPageHasOtherContent($file));

		$subfolder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$subfolder->method('getDirectoryListing')
			->willReturn([$parentFolder]);
		$subfolder->method('getName')
			->willReturn('subfolder');
		$subfolder->method('nodeExists')
			->with('Readme.md')
			->willReturn(true);
		$folder = $this->getMockBuilder(Folder::class)
			->disableOriginalConstructor()
			->getMock();
		$folder->method('getDirectoryListing')
			->willReturn([$subfolder]);

		// Test `folderHasSubPages()` with page in grandchildren
		self::assertTrue(NodeHelper::folderHasSubPages($folder));
		// Test `folderHasSubPages()` without page in grandchildren
		self::assertFalse(NodeHelper::folderHasSubPages($folder));

		// Test `folderHasSubPage()`
		self::assertEquals(0, NodeHelper::folderHasSubPage($parentFolder, 'File3'));
		self::assertEquals(1, NodeHelper::folderHasSubPage($parentFolder, 'File2'));
		self::assertEquals(2, NodeHelper::folderHasSubPage($folder, 'subfolder'));
	}

	public function linksContentProvider(): array {
		return [
			// Valid link syntax
			['#Title\n\nLink: [link](https://example.org/)\n\nMore text...', [['link', 'https://example.org/', '']]],
			['[link](https://example.org/ (title))', [['link', 'https://example.org/', 'title']]],
			['[link](https://example.org/ "title")', [['link', 'https://example.org/', 'title']]],
			['[link](https://example.org/ "my \"title\"")', [['link', 'https://example.org/', 'my "title"']]],
			['[link](https://example.org/ \'title\')', [['link', 'https://example.org/', 'title']]],
			['[link](https://example.org/ \'my "title"\')', [['link', 'https://example.org/', 'my "title"']]],
			['[link](/uri)', [['link', '/uri', '']]],
			['[link](/uri (my title))', [['link', '/uri', 'my title']]],
			['[link](/uri "my title")', [['link', '/uri', 'my title']]],
			['[](./target.md)', [['', './target.md', '']]],
			['[link](<>)', [['link', '', '']]],
			['[link]()', [['link', '', '']]],
			['[link](</my uri>)', [['link', '/my%20uri', '']]],
			['<https://example.org/>', [['https://example.org/', 'https://example.org/', '']]],
			['[link](foo(and(bar)))', [['link', 'foo(and(bar))', '']]],
			['[link](https://example.org/?foo=3#fragment)', [['link', 'https://example.org/?foo=3#fragment', '']]],
			['[link](#fragment)', [['link', '#fragment', '']]],

			// With markdown marks in text
			['#Title\n\nLink: [*italic* **bold** link](https://example.org/)\n\nMore text...', [['italic bold link', 'https://example.org/', '']]],

			// Multiple links
			['#Title\n\nLink1: [link1](https://example.org/)\n\n> <https://example.com>\n\n[link3](/link3.md (preview))', [
				['link1', 'https://example.org/', ''],
				['https://example.com', 'https://example.com', ''],
				['link3', '/link3.md', 'preview'],
			]],

			// Invalid link syntax
			['[link(/uri)', []],
			['[link](<foo\\>)', []],
			['[link](https://example.org/ "my "title"")', []],
			['<Readme.md>', []],
			['</Readme.md>', []],
		];
	}

	/**
	 * @dataProvider linksContentProvider
	 */
	public function testGetLinksFromContent(string $content, array $linksProps): void {
		$links = [];
		foreach ($linksProps as $linkProps) {
			$links[] = ['text' => $linkProps[0], 'href' => $linkProps[1], 'title' => $linkProps[2]];
		}
		self::assertEquals($links, NodeHelper::getLinksFromContent($content));
	}

	public function testGetLinkedPageIds(): void {
		$trustedDomains = ['nextcloud.local'];

		$collective = new Collective();
		$collective->setId(42);
		$collective->setName('mycollective');
		$collective->setSlug('mycollective');

		$pageInfo = new PageInfo();
		$pageInfo->setId(123);
		$pageInfo->setCollectivePath('Collectives/' . $collective->getName());
		$pageInfo->setFilePath('page1/pageX');
		$pageInfo->setFileName('subpage2.md');
		$pageInfo->setTitle('subpage2');
		$pageInfo->setSlug('subpage2');

		$urlSlugPathBase = '/apps/collectives/' . $collective->getUrlPath();
		$urlPathBase = '/apps/collectives/' . $collective->getName();

		$urlSlugPath = $urlSlugPathBase . '/' . $pageInfo->getUrlPath();
		$urlPathPageSlug = $urlPathBase . '/' . $pageInfo->getUrlPath();
		$urlPath = $urlPathBase . '/' . $pageInfo->getFilePath() . '/' . $pageInfo->getTitle();

		$content = '# Title

* Page1: [page1](https://nextcloud.local/apps/collectives/mycollective/some/page1?fileId=123)
* Page2: [page2](https://nextcloud.local/apps/collectives/mycollective-42/page2-slug-22#heading)
* Page5: [page5](./page5-25?foo=bar)

[page3](https://nextcloud.local/apps/collectives/mycollective-42/page3-slug-23#heading (preview))

[page4](/apps/collectives/mycollective-42/page4-slug-24 (preview))

[page5 second time](./page5?fileId=25 (preview))
';

		// Multiple link formats in one content
		self::assertEquals([123, 22, 25, 23, 24], NodeHelper::getLinkedPageIds($collective, $content, $trustedDomains));

		// Absolute link with fileId in [text](link) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](http://nextcloud.local' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](http://nextcloud.local' . $urlSlugPath . ') in it.', $trustedDomains));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](https://nextcloud.local' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](https://nextcloud.local/index.php' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link to wrong host (with fileId)](https://example.org/' . $urlPath . 'fileId=123) in it.', $trustedDomains));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link with many slashes](https://nextcloud.local/////' . str_replace('/', '//', $urlSlugPath) . ') in it.', $trustedDomains));

		// Absolute link with slug in [text](link) syntax
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link to wrong instance](https://nextcloud.local/instance2' . $urlPath . ') in it.', $trustedDomains));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link to wrong host](https://anothercloud.com' . $urlPath . ') in it.', $trustedDomains));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link to wrong host](anothercloud.com' . $urlPath . ') in it.', $trustedDomains));

		// Root-relative link with slug in [text](/apps/collectives/mycollective-42/subpage2-123) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](' . $urlSlugPath . ')'));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](' . $urlPathPageSlug . ')'));

		// Root-relative link with fileId in [text](/apps/collectives/mycollective/subpage2?fileId=123) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a root-relative link](' . $urlPath . '?fileId=123).'));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](/index.php' . $urlPath . '?fileId=123).'));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](' . $urlPathBase . '/subpage2?fileId=123).'));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a broken link(' . $urlPath . '?fileId=123).'));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a broken link] (' . $urlPath . '?fileId=123).'));

		// Root-relative link to wrong collective
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link](/apps/collectives/other/' . $pageInfo->getUrlPath() . ')'));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link](/apps/collectives/mycollective-99/' . $pageInfo->getUrlPath() . ')'));

		// Root-relative link to something else
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link](/apps/other/mycollective-42/' . $pageInfo->getUrlPath() . ')'));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link to wrong webroot](/index.php/instance2' . $urlPath . ') in it.'));

		// Relative link with slug
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](pageX/subpage2-123).'));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](../subpage2-123).'));
		// Relative link with pageId
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](pageX/subpage2?fileId=123).'));
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](../subpage2?fileId=123).'));

		// Relative link without slug or pageId
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link](pageX/subpage2).'));

		// Relative link with fileId in [text](link (preview)) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link](https://nextcloud.local' . $urlSlugPath . ' (preview)) in it.', $trustedDomains));

		OC::$WEBROOT = '/mycloud';

		// Relative link with fileId with webroot in [text](link) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link with webroot](/' . OC::$WEBROOT . $urlPath . '?fileId=123).', $trustedDomains));
		// Relative link with slug with webroot in [text](link) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link with webroot](' . OC::$WEBROOT . $urlSlugPath . ') in it.'));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link with missing webroot](' . $urlPath . ') in it.'));
		// Absolute link with fileId with webroot in [text](link) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link with webroot](http://nextcloud.local' . OC::$WEBROOT . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link with missing webroot](http://nextcloud.local' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		// Absolute link with slug with webroot in [text](link) syntax
		self::assertEquals([123], NodeHelper::getLinkedPageIds($collective, 'content with [a link with webroot](http://nextcloud.local' . OC::$WEBROOT . $urlSlugPath . ') in it.', $trustedDomains));
		self::assertEquals([], NodeHelper::getLinkedPageIds($collective, 'content with [a link with missing webroot](http://nextcloud.local' . $urlSlugPath . ') in it.', $trustedDomains));
	}
}
