<?php

namespace OCA\Collectives\Reference;

use DateTime;
use OC\Collaboration\Reference\LinkReferenceProvider;
use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OC\Collaboration\Reference\ReferenceManager;
use OCA\Collectives\AppInfo\Application;
use OCP\Collaboration\Reference\IReference;
use OCP\Files\File;
use OCP\IConfig;
use OCP\IDateTimeFormatter;
use OCP\IL10N;

use OCP\IURLGenerator;

class PageReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {
	private const RICH_OBJECT_TYPE = Application::APP_NAME . '_page';

	private ?string $userId;
	private IConfig $config;
	private ReferenceManager $referenceManager;
	private IL10N $l10n;
	private IURLGenerator $urlGenerator;
	private LinkReferenceProvider $linkReferenceProvider;
	private PageService $pageService;
	private CollectiveService $collectiveService;
	private IDateTimeFormatter $dateTimeFormatter;

	public function __construct(IConfig $config,
								CollectiveService $collectiveService,
								PageService $pageService,
								IL10N $l10n,
								IURLGenerator $urlGenerator,
								IDateTimeFormatter $dateTimeFormatter,
								ReferenceManager $referenceManager,
								LinkReferenceProvider $linkReferenceProvider,
								?string $userId) {
		$this->userId = $userId;
		$this->config = $config;
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
	public function getId(): string {
		return Application::APP_NAME . '-ref-pages';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Collective pages');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->urlGenerator->getAbsoluteURL(
			$this->urlGenerator->imagePath(Application::APP_NAME, 'collectives-dark.svg')
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getSupportedSearchProviderIds(): array {
		return ['collectives-pages'];
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
			$pageInfo = $this->getPagePathFromDirectLink($referenceText);
			if ($pageInfo !== null) {
				// TODO should it work if the fileId GET param is not there?
				if (isset($pageInfo['fileId'])) {
					$pageFileId = $pageInfo['fileId'];
					$collectiveName = $pageInfo['collectiveName'];
					$collectives = $this->collectiveService->getCollectives($this->userId);
					$collectives = array_filter($collectives, static function (CollectiveInfo $c) use ($collectiveName) {
						return $c->getName() === $collectiveName;
					});
					if (!empty($collectives)) {
						$collective = end($collectives);
						$pageInfo['collective'] = $collective;
						$collectiveFolder = $this->pageService->getCollectiveFolder($collective->getId(), $this->userId);
						$pageFile = $collectiveFolder->getById($pageFileId);
						if (!empty($pageFile) && isset($pageFile[0]) && $pageFile[0] instanceof File) {
							$pageFile = $pageFile[0];
							$pageInfo['page'] = $this->pageService->findByFile($collective->getId(), $pageFile, $this->userId);

							$reference = new Reference($referenceText);

							$reference->setTitle($pageInfo['page']->getEmoji() . ' ' . $pageInfo['page']->getTitle());

							$description = $this->l10n->t('In collective %1$s %2$s', [$collective->getEmoji(), $collective->getName()])
								. ' - ' . $pageInfo['page']->getFilePath();
							$reference->setDescription($description);
							$pageInfo['description'] = $description;

							$date = new DateTime();
							$date->setTimestamp($pageInfo['page']->getTimestamp());
							$formattedRelativeDate = $this->dateTimeFormatter->formatTimeSpan($date);
							$pageInfo['lastEdited'] = $this->l10n->t('Last edition %1$s', [$formattedRelativeDate]);

							$imageUrl = $this->urlGenerator->getAbsoluteURL(
								$this->urlGenerator->imagePath(Application::APP_NAME, 'page.svg')
							);
							$reference->setImageUrl($imageUrl);

							$pageInfo['link'] = $referenceText;
							$reference->setUrl($referenceText);

							$reference->setRichObject(
								self::RICH_OBJECT_TYPE,
								$pageInfo,
							);
							return $reference;
						}
					}
				}
			}
			// fallback to opengraph if it matches but somehow we can't resolve
			return $this->linkReferenceProvider->resolveReference($referenceText);
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
				'collectiveName' => $matches[1],
				'pagePath' => $matches[2],
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
