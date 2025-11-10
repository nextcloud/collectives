<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Fs;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use OC;
use OCA\Collectives\Db\Collective;

class MarkdownHelper {
	/**
	 * Recursively collect text from a CommonMark node and its descendants
	 */
	private static function collectText(Node $node): string {
		if ($node instanceof Text) {
			return $node->getLiteral();
		}

		$out = '';
		foreach ($node->children() as $child) {
			$out .= self::collectText($child);
		}

		return $out;
	}

	/**
	 * Extracts markdown links and returns them with link text, href and title
	 *
	 * @throws CommonMarkException
	 */
	public static function getLinksFromContent(string $content): array {
		$environment = new Environment();
		$environment->addExtension(new CommonMarkCoreExtension());
		$parser = new MarkdownParser($environment);
		$document = $parser->parse($content);
		$walker = $document->walker();

		$links = [];
		while ($event = $walker->next()) {
			if (! $event->isEntering()) {
				continue;
			}
			$node = $event->getNode();
			if (!($node instanceof Link)) {
				continue;
			}

			$textParts = [];
			foreach ($node->children() as $child) {
				$textParts[] = self::collectText($child);
			}
			$text = trim(implode('', array_filter($textParts)));

			$links[] = [
				'text' => $text,
				'href' => $node->getUrl(),
				'title' => $node->getTitle() ?? '',
			];
		}

		return $links;
	}

	/**
	 * Returns hrefs that point to given collective or are relative links (.e.g. `../Page-21`)
	 */
	private static function getHrefsToCollectiveFromLinks(array $links, Collective $collective, array $trustedDomains): array {
		// Absolute URL regex
		$protocol = '^https?:\/\/';
		$trustedDomainsArray = array_map(static fn (string $domain) => str_replace('\*', '\w*', preg_quote($domain, '/')), $trustedDomains);
		$trustedDomainsPart = $trustedDomainsArray !== [] ? '(?:' . implode('|', $trustedDomainsArray) . ')' : 'localhost';
		$absoluteUrlRegex = '/' . $protocol . $trustedDomainsPart . '(?::[0-9]+)?(.+)$/';

		// Root-relative URL regex
		$ocWebrootPath = OC::$WEBROOT ? '\/+' . preg_quote(str_replace('/', '/+', trim(OC::$WEBROOT, '/')), '/') : '';
		$basePath = $ocWebrootPath . '(?:\/+index\.php)?';
		$appPath = '\/+apps\/+collectives\/+';
		$collectivePath = '(?:' . implode('|', [
			// slug may only contain ascii characters
			'[\x00-\x7F]+\-' . $collective->getId(),
			rawurlencode($collective->getName()),
		]) . ')(?=\/+)';
		$rootRelativeUrlRegex = '/^' . $basePath . $appPath . $collectivePath . '(.+)$/';

		$hrefs = [];
		foreach ($links as $link) {
			$href = $link['href'];
			if (!$href) {
				continue;
			}

			// Absolute URL (with protocol)
			if (preg_match('/^[a-zA-Z]+:\/\//', $href)) {
				// absolute link
				if (!preg_match($absoluteUrlRegex, $href, $matches)) {
					// Absolute link doesn't point to Nextcloud instance
					continue;
				}
				$href = $matches[1];
			}

			// Root-relative URL
			if (str_starts_with($href, '/')) {
				if (preg_match($rootRelativeUrlRegex, $href, $matches)) {
					$href = $matches[1];
				} else {
					// Root-relative URL doesn't point to the collective
					continue;
				}
			}

			$hrefs[] = $href;
		}

		return $hrefs;
	}

	/**
	 * Extracts pageIds from (absolute and relative) links to pages (in slug and fileId syntax)
	 */
	private static function getPageIdsFromHrefs(array $hrefs): array {
		// slug may only contain ascii characters
		$slugFragmentSuffix = '(?:[?#].*)?$/';
		$slugPagePathRegex = '/\/[\x00-\x7F]+\-([0-9]+)' . $slugFragmentSuffix;
		$noSlugFragmentSuffix = '(?:[&#].*)?$/';
		$noSlugPagePathRegex = '/^.*[^?]+\?fileId=([0-9]+)' . $noSlugFragmentSuffix;

		$pageIds = [];
		foreach ($hrefs as $href) {
			if (preg_match($slugPagePathRegex, $href, $matches)) {
				$pageIds[] = (int)$matches[1];
			} elseif (preg_match($noSlugPagePathRegex, $href, $matches)) {
				$pageIds[] = (int)$matches[1];
			}
		}

		return $pageIds;
	}

	/**
	 * @throws CommonMarkException
	 */
	public static function getLinkedPageIds(Collective $collective, string $content, array $trustedDomains = []): array {
		$links = self::getLinksFromContent($content);
		$collectiveHrefs = self::getHrefsToCollectiveFromLinks($links, $collective, $trustedDomains);
		$pageIds = self::getPageIdsFromHrefs($collectiveHrefs);

		return array_unique($pageIds, SORT_NUMERIC);
	}
}
