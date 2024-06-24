<?php

declare(strict_types=1);

namespace OCA\Collectives\Reference;

use DateTime;
use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Collectives\AppInfo\Application;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\PageService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IDateTimeFormatter;
use OCP\IL10N;
use OCP\IURLGenerator;
use Throwable;

class SearchablePageReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	private const RICH_OBJECT_TYPE = Application::APP_NAME . '_page';

	public function __construct(private CollectiveService $collectiveService,
		private PageService $pageService,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
		private IDateTimeFormatter $dateTimeFormatter,
		private ReferenceManager $referenceManager,
		private LinkReferenceProvider $linkReferenceProvider,
		private ?string $userId) {
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

	public function matchReference(string $referenceText): bool {
		if ($this->userId === null) {
			return false;
		}
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_NAME);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_NAME);

		// link example:
		// https://nextcloud.local/apps/collectives/supacollective/Tutos/Hacking/Spectre?fileId=14457
		// https://nextcloud.local/apps/collectives/supacollective/Tutos/Hacking/Spectre
		$noIndexMatch = preg_match('/^' . preg_quote($start, '/') . '\/[^\/]+\//i', $referenceText) === 1;
		$indexMatch = preg_match('/^' . preg_quote($startIndex, '/') . '\/[^\/]+\//i', $referenceText) === 1;

		return $noIndexMatch || $indexMatch;
	}

	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$pageReferenceInfo = $this->getPagePathFromDirectLink($referenceText);
			if (!$pageReferenceInfo) {
				// fallback to opengraph if it matches, but somehow we can't resolve
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$collectiveName = $pageReferenceInfo['collectiveName'];
			try {
				$collective = $this->collectiveService->findCollectiveByName($this->userId, $collectiveName);
				if (isset($pageReferenceInfo['fileId'])) {
					$page = $this->pageService->findByFileId($collective->getId(), $pageReferenceInfo['fileId'], $this->userId);
				} else {
					try {
						$page = $this->pageService->findByPath($collective->getId(), $pageReferenceInfo['pagePath'], $this->userId);
					} catch (NotFoundException) {
						$pathInfo = pathinfo($pageReferenceInfo['pagePath']);
						if (!$pathInfo || !array_key_exists('extension', $pathInfo)) {
							throw new NotFoundException('Pathinfo for page path is incomplete');
						}
						if ('.' . $pathInfo['extension'] === PageInfo::SUFFIX) {
							if ($pathInfo['filename'] === PageInfo::INDEX_PAGE_TITLE) {
								// try to find page by stripping `/Readme.md`
								$page = $this->pageService->findByPath($collective->getId(), $pathInfo['dirname'], $this->userId);
							} else {
								// try to find page by stripping `.md`
								$page = $this->pageService->findByPath($collective->getId(), $pathInfo['filename'], $this->userId);
							}
						}
					}
				}
			} catch (Exception | Throwable) {
				// fallback to opengraph if it matches, but somehow we can't resolve
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$pageReferenceInfo['collective'] = $collective;
			$pageReferenceInfo['page'] = $page;

			$link = $this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . $this->pageService->getPageLink($collective->getName(), $page);
			$reference = new Reference($link);
			$pageEmoji = $page->getEmoji();
			$refTitle = $pageEmoji ? $pageEmoji . ' ' . $page->getTitle() : $page->getTitle();
			$reference->setTitle($refTitle);

			$description = $this->l10n->t('In collective %1$s', [$this->collectiveService->getCollectiveNameWithEmoji($collective)])
				. ' - ' . $page->getFilePath();
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

		return null;
	}

	public function getPagePathFromDirectLink(string $url): ?array {
		$start = $this->urlGenerator->getAbsoluteURL('/apps/' . Application::APP_NAME);
		$startIndex = $this->urlGenerator->getAbsoluteURL('/index.php/apps/' . Application::APP_NAME);

		preg_match('/^' . preg_quote($start, '/') . '\/([^\/]+)\/([^?]+)/i', $url, $matches);
		if (!$matches || count($matches) < 3) {
			preg_match('/^' . preg_quote($startIndex, '/') . '\/([^\/]+)\/([^?]+)/i', $url, $matches);
		}
		if ($matches && count($matches) > 2) {
			$pagePath = [
				'collectiveName' => urldecode($matches[1]),
				'pagePath' => urldecode($matches[2]),
			];
			preg_match('/\?fileId=(\d+)$/i', $url, $matches);
			if ($matches && count($matches) > 1) {
				$pagePath['fileId'] = (int) $matches[1];
			}
			return $pagePath;
		}

		return null;
	}

	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
