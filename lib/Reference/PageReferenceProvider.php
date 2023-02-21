<?php

namespace OCA\Collectives\Reference;

use DateTime;
use Exception;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Collectives\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\IDateTimeFormatter;
use OCP\IL10N;

use OCP\IURLGenerator;
use Throwable;

class PageReferenceProvider implements IReferenceProvider {
	private const RICH_OBJECT_TYPE = Application::APP_NAME . '_page';

	private ?string $userId;
	private ReferenceManager $referenceManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;
	private PageService $pageService;
	private CollectiveService $collectiveService;
	private IDateTimeFormatter $dateTimeFormatter;

	public function __construct(CollectiveService $collectiveService,
								PageService $pageService,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								IDateTimeFormatter $dateTimeFormatter,
								ReferenceManager $referenceManager,
								LinkReferenceProvider $linkReferenceProvider,
								?string $userId) {
		$this->userId = $userId;
		$this->referenceManager = $referenceManager;
		$this->l10n = $l10n;
		$this->urlGenerator = $urlGenerator;
		$this->linkReferenceProvider = $linkReferenceProvider;
		$this->pageService = $pageService;
		$this->collectiveService = $collectiveService;
		$this->dateTimeFormatter = $dateTimeFormatter;
	}

	/**
	 * @inheritDoc
	 */
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

	/**
	 * @inheritDoc
	 */
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$pageReferenceInfo = $this->getPagePathFromDirectLink($referenceText);
			// TODO should it work if the fileId GET param is not there?
			if (!$pageReferenceInfo || !isset($pageReferenceInfo['fileId'])) {
				// fallback to opengraph if it matches but somehow we can't resolve
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}

			$pageFileId = $pageReferenceInfo['fileId'];
			$collectiveName = $pageReferenceInfo['collectiveName'];
			try {
				$collective = $this->collectiveService->findCollectiveByName($this->userId, $collectiveName);
				$page = $this->pageService->findByFileId($collective->getId(), $pageFileId, $this->userId);
			} catch (Exception | Throwable $e) {
				// fallback to opengraph if it matches but somehow we can't resolve
				return $this->linkReferenceProvider->resolveReference($referenceText);
			}
			$pageReferenceInfo['collective'] = $collective;
			$pageReferenceInfo['page'] = $page;

			$reference = new Reference($referenceText);
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

			$pageReferenceInfo['link'] = $referenceText;
			$reference->setUrl($referenceText);

			$reference->setRichObject(
				self::RICH_OBJECT_TYPE,
				$pageReferenceInfo,
			);
			return $reference;
		}

		return null;
	}

	/**
	 * @param string $url
	 * @return array|null
	 */
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

	/**
	 * @inheritDoc
	 */
	public function getCachePrefix(string $referenceId): string {
		return $this->userId ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey(string $referenceId): ?string {
		return $referenceId;
	}

	/**
	 * @param string $userId
	 * @return void
	 */
	public function invalidateUserCache(string $userId): void {
		$this->referenceManager->invalidateCache($userId);
	}
}
