<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Reference;

use DateTime;
use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SharePageService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IPublicReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use Throwable;

class SearchablePageReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider, IPublicReferenceProvider {
	private const RICH_OBJECT_TYPE = Application::APP_NAME . '_page';

	public function __construct(
		private CollectiveService $collectiveService,
		private PageService $pageService,
		private SharePageService $sharePageService,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IDateTimeFormatter $dateTimeFormatter,
		private ReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private ?string $userId,
	) {
	}

	public function getId(): string {
		return Application::APP_NAME . '-ref-pages';
	}

	public function getTitle(): string {
		return $this->l10n->t('Collective pages');
	}

	public function getOrder(): int {
		return 10;
	}

	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_NAME, 'collectives-dark.svg')
		);
	}

	public function getSupportedSearchProviderIds(): array {
		return ['collectives-pages'];
	}

	private static function pagePathFromMatches(string $url, array $matches): array {
		$pagePath = [
			'collectiveName' => urldecode($matches[1]),
			'pagePath' => urldecode(ltrim($matches[2] ?? '', '/')),
		];

		// URL with fileId query param
		preg_match('/\?fileId=(\d+)$/i', $url, $matches);
		if ($matches && count($matches) > 1) {
			$pagePath['fileId'] = (int)$matches[1];
		}

		// Slugified page URL path
		if (preg_match('/(.+?)-(\d+)$/', $pagePath['pagePath'], $matches)) {
			$pagePath['pagePath'] = urldecode($matches[0]);
			$pagePath['fileId'] = (int)$matches[2];
		}

		// Slugified collectives URL path
		if (preg_match('/(.+?)-(\d+)$/', $pagePath['collectiveName'], $matches)) {
			$pagePath['collectiveName'] = $matches[1];
			$pagePath['collectiveId'] = (int)$matches[2];
		}

		return $pagePath;
	}

	public function matchUrl(string $url): ?array {
		// link examples:
		// https://nextcloud.local/apps/collectives/p/MsdwSCmP9F6jcQX/supacollective-123
		// https://nextcloud.local/apps/collectives/p/MsdwSCmP9F6jcQX/supacollective-123/spectre-slug-14457
		// https://nextcloud.local/apps/collectives/p/MsdwSCmP9F6jcQX/supacollective/Tutos/Hacking/Spectre?fileId=14457
		// https://nextcloud.local/apps/collectives/p/MsdwSCmP9F6jcQX/supacollective/Tutos/Hacking/Spectre
		// https://nextcloud.local/index.php/apps/collectives/p/supacollective/MsdwSCmP9F6jcQX/Tutos/Hacking/Spectre?fileId=14457
		$startPublicRegexes = [
			$this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_NAME . '/p'),
			$this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_NAME . '/p'),
		];

		$matches = false;
		foreach ($startPublicRegexes as $regex) {
			preg_match('/^' . preg_quote($regex, '/') . '\/\w+' . '\/([^\/]+)(\/[^?]+)?/i', $url, $matches);
			if ($matches && count($matches) > 1) {
				return self::pagePathFromMatches($url, $matches);
			}
		}

		// link examples:
		// https://nextcloud.local/apps/collectives/supacollective-123
		// https://nextcloud.local/apps/collectives/supacollective-123/spectre-slug-14457
		// https://nextcloud.local/apps/collectives/supacollective/Tutos/Hacking/Spectre?fileId=14457
		// https://nextcloud.local/apps/collectives/supacollective/Tutos/Hacking/Spectre
		$startRegexes = [
			$this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_NAME),
			$this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_NAME),
		];

		foreach ($startRegexes as $regex) {
			preg_match('/^' . preg_quote($regex, '/') . '\/([^\/]+)(\/[^?]+)?/i', $url, $matches);
			if ($matches && count($matches) > 1) {
				return self::pagePathFromMatches($url, $matches);
			}
		}

		return null;
	}

	public function matchReference(string $referenceText): bool {
		return (bool)$this->matchUrl($referenceText);
	}

	/**
	 * @throws NotFoundException
	 */
	private function getCollective(?int $collectiveId, string $collectiveName, ?string $sharingToken): Collective {
		if ($sharingToken) {
			// TODO: Check if share is password protected; if yes, then check in session if authenticated
			return $this->collectiveService->findCollectiveByShare($sharingToken);
		}

		if ($collectiveId) {
			return $this->collectiveService->getCollective($collectiveId, $this->userId);
		}

		return $this->collectiveService->findCollectiveByName($this->userId, $collectiveName);
	}

	/**
	 * @throws NotFoundException
	 */
	private function getPage(Collective $collective, array $pageReferenceInfo, bool $public): PageInfo {
		if ($public && !$collective->getShareToken()) {
			throw new NotFoundException('Collective share token is missing');
		}

		if (isset($pageReferenceInfo['fileId'])) {
			$page = $public
				? $this->sharePageService->findSharePageById($collective->getShareToken(), $pageReferenceInfo['fileId'])
				: $this->pageService->findByFileId($collective->getId(), $pageReferenceInfo['fileId'], $this->userId);
		} else {
			try {
				$page = $public
					? $this->sharePageService->findSharePageByPath($collective->getShareToken(), $pageReferenceInfo['pagePath'])
					: $this->pageService->findByPath($collective->getId(), $pageReferenceInfo['pagePath'], $this->userId);
			} catch (NotFoundException) {
				$pathInfo = pathinfo($pageReferenceInfo['pagePath']);
				if (!$pathInfo || !array_key_exists('extension', $pathInfo)) {
					throw new NotFoundException('Pathinfo for page path is incomplete');
				}
				if ('.' . $pathInfo['extension'] === PageInfo::SUFFIX) {
					if ($pathInfo['filename'] === PageInfo::INDEX_PAGE_TITLE) {
						// try to find page by stripping `/Readme.md`
						$page = $public
							? $this->sharePageService->findSharePageByPath($collective->getShareToken(), $pathInfo['dirname'])
							: $this->pageService->findByPath($collective->getId(), $pathInfo['dirname'], $this->userId);
					} else {
						// try to find page by stripping `.md`
						$page = $public
							? $this->sharePageService->findSharePageByPath($collective->getShareToken(), $pathInfo['filename'])
							: $this->pageService->findByPath($collective->getId(), $pathInfo['filename'], $this->userId);
					}
				} else {
					throw new NotFoundException('Pathinfo for page path is incomplete');
				}
			}
		}

		return $page;
	}

	private function resolve(string $referenceText, bool $public = false, string $sharingToken = ''): ?IReference {
		if (!$this->matchReference($referenceText)) {
			return null;
		}

		$pageReferenceInfo = $this->getPagePathFromDirectLink($referenceText);
		if (!$pageReferenceInfo) {
			// fallback to opengraph if it matches, but somehow we can't resolve
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}

		$collectiveId = $pageReferenceInfo['collectiveId'] ?? null;
		$collectiveName = $pageReferenceInfo['collectiveName'];

		if ($public && !$sharingToken) {
			// fallback to opengraph for public lookups without share token
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}
		try {
			$collective = $this->getCollective($collectiveId, $collectiveName, $sharingToken);
			$page = $this->getPage($collective, $pageReferenceInfo, $public);
		} catch (Exception|Throwable) {
			// fallback to opengraph if it matches, but somehow we can't resolve
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}

		$pageReferenceInfo['collective'] = $collective;
		$pageReferenceInfo['page'] = $page;

		$collectivesLink = $this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . ($public ? 'p/' . $sharingToken . '/' : '');
		$link = $collectivesLink . $this->pageService->getPageLink($collective->getUrlPath(), $page);
		if (str_contains($referenceText, '#')) {
			$link .= '#' . explode('#', $referenceText)[1];
		}
		$reference = new Reference($link);
		$pageEmoji = $page->getEmoji();
		$refTitle = $pageEmoji ? $pageEmoji . ' ' . $page->getTitle() : $page->getTitle();
		$reference->setTitle($refTitle);

		$descriptionSuffix = $page->getFilePath()
			? ' - ' . $page->getFilePathString()
			: '';
		$description = $this->l10n->t('In collective %1$s', [$this->collectiveService->getCollectiveNameWithEmoji($collective)])
			. $descriptionSuffix;
		$reference->setDescription($description);
		$pageReferenceInfo['description'] = $description;

		$date = new DateTime();
		$date->setTimestamp($page->getTimestamp());
		$formattedRelativeDate = $this->dateTimeFormatter->formatTimeSpan($date);
		$pageReferenceInfo['lastEdited'] = $this->l10n->t('Last edition %1$s', [$formattedRelativeDate]);

		$imageUrl = $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_NAME, 'page.svg')
		);
		$reference->setImageUrl($imageUrl);

		$pageReferenceInfo['link'] = $link;
		$reference->setUrl($link);

		$reference->setRichObject(
			self::RICH_OBJECT_TYPE,
			$pageReferenceInfo,
		);
		return $reference;
	}

	public function resolveReference(string $referenceText): ?IReference {
		if ($this->userId === null) {
			return $this->linkReferenceProvider->resolveReference($referenceText);
		}
		return $this->resolve($referenceText);
	}

	public function resolveReferencePublic(string $referenceText, string $sharingToken): ?IReference {
		return $this->resolve($referenceText, true, $sharingToken);
	}

	public function getPagePathFromDirectLink(string $url): ?array {
		return $this->matchUrl($url);
	}

	public function getCachePrefix(string $referenceId): string {
		return $referenceId;
	}

	private function getCollectiveIdPrefix(string $referenceId, ?string $sharingToken = null): string {
		$pageReferenceInfo = $this->getPagePathFromDirectLink($referenceId);
		$collectiveId = $pageReferenceInfo['collectiveId'] ?? null;
		if (!$collectiveId) {
			try {
				$collective = $this->getCollective(null, $pageReferenceInfo['collectiveName'], $sharingToken);
				$collectiveId = $collective->getId();
			} catch (NotFoundException) {
			}
		}
		return $collectiveId ? $collectiveId . '_' : '';
	}

	public function getCacheKey(string $referenceId): ?string {
		return $this->getCollectiveIdPrefix($referenceId) . ($this->userId ?? '');
	}

	public function getCacheKeyPublic(string $referenceId, string $sharingToken): ?string {
		return $this->getCollectiveIdPrefix($referenceId, $sharingToken) . $sharingToken;
	}

	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
