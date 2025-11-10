<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace Unit\Fs;

use OC;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Fs\MarkdownHelper;
use OCA\Collectives\Model\PageInfo;
use PHPUnit\Framework\TestCase;

class MarkdownHelperTest extends TestCase {
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
		self::assertEquals($links, MarkdownHelper::getLinksFromContent($content));
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
		self::assertEquals([123, 22, 25, 23, 24], MarkdownHelper::getLinkedPageIds($collective, $content, $trustedDomains));

		// Absolute link with fileId in [text](link) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](http://nextcloud.local' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](http://nextcloud.local' . $urlSlugPath . ') in it.', $trustedDomains));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](https://nextcloud.local' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](https://nextcloud.local/index.php' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link to wrong host (with fileId)](https://example.org/' . $urlPath . 'fileId=123) in it.', $trustedDomains));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with many slashes](https://nextcloud.local/////' . str_replace('/', '//', $urlSlugPath) . ') in it.', $trustedDomains));

		// Absolute link with slug in [text](link) syntax
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link to wrong instance](https://nextcloud.local/instance2' . $urlPath . ') in it.', $trustedDomains));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link to wrong host](https://anothercloud.com' . $urlPath . ') in it.', $trustedDomains));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link to wrong host](anothercloud.com' . $urlPath . ') in it.', $trustedDomains));

		// Root-relative link with slug in [text](/apps/collectives/mycollective-42/subpage2-123) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](' . $urlSlugPath . ')'));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](' . $urlPathPageSlug . ')'));

		// Root-relative link with fileId in [text](/apps/collectives/mycollective/subpage2?fileId=123) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a root-relative link](' . $urlPath . '?fileId=123).'));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](/index.php' . $urlPath . '?fileId=123).'));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](' . $urlPathBase . '/subpage2?fileId=123).'));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a broken link(' . $urlPath . '?fileId=123).'));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a broken link] (' . $urlPath . '?fileId=123).'));

		// Root-relative link to wrong collective
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](/apps/collectives/other/' . $pageInfo->getUrlPath() . ')'));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](/apps/collectives/mycollective-99/' . $pageInfo->getUrlPath() . ')'));

		// Root-relative link to something else
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](/apps/other/mycollective-42/' . $pageInfo->getUrlPath() . ')'));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link to wrong webroot](/index.php/instance2' . $urlPath . ') in it.'));

		// Relative link with slug
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](pageX/subpage2-123).'));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](../subpage2-123).'));
		// Relative link with pageId
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](pageX/subpage2?fileId=123).'));
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](../subpage2?fileId=123).'));

		// Relative link without slug or pageId
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](pageX/subpage2).'));

		// Relative link with fileId in [text](link (preview)) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link](https://nextcloud.local' . $urlSlugPath . ' (preview)) in it.', $trustedDomains));

		OC::$WEBROOT = '/mycloud';

		// Relative link with fileId with webroot in [text](link) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with webroot](/' . OC::$WEBROOT . $urlPath . '?fileId=123).', $trustedDomains));
		// Relative link with slug with webroot in [text](link) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with webroot](' . OC::$WEBROOT . $urlSlugPath . ') in it.'));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with missing webroot](' . $urlPath . ') in it.'));
		// Absolute link with fileId with webroot in [text](link) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with webroot](http://nextcloud.local' . OC::$WEBROOT . $urlPath . '?fileId=123) in it.', $trustedDomains));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with missing webroot](http://nextcloud.local' . $urlPath . '?fileId=123) in it.', $trustedDomains));
		// Absolute link with slug with webroot in [text](link) syntax
		self::assertEquals([123], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with webroot](http://nextcloud.local' . OC::$WEBROOT . $urlSlugPath . ') in it.', $trustedDomains));
		self::assertEquals([], MarkdownHelper::getLinkedPageIds($collective, 'content with [a link with missing webroot](http://nextcloud.local' . $urlSlugPath . ') in it.', $trustedDomains));
	}
}
