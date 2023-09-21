<?php

namespace OCA\Collectives\Dashboard;

use OCA\Collectives\Model\CollectiveInfo;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;

class RecentPagesWidget implements IReloadableWidget {
	public const REFRESH_INTERVAL_IN_SECS = 33;
	public const MAX_ITEMS = 10;

	public function __construct(
		protected IL10N $l10n,
		protected IURLGenerator $urlGenerator,
		protected IUserSession $userSession,
		protected PageService $pageService,
		protected CollectiveService $collectiveService,
	) {}

	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		if (!($user = $this->userSession->getUser())) {
			return new WidgetItems();
		}

		$collectives = $this->collectiveService->getCollectives($user->getUID());
		$results  = [];
		foreach ($collectives as $collective) {
			// fetch pages from the current collective
			$id = $collective->getId();
			$pages = $this->pageService->findAll($id, $user->getUID());

			// sort pages and slice to the maximal necessary amount
			usort($pages, function (PageInfo $a, PageInfo $b): int {
				return $b->getTimestamp() - $a->getTimestamp();
			});
			$pages = array_slice($pages, 0, self::MAX_ITEMS);

			// prepare result entries
			foreach ($pages as $page) {
				$results[] = [
					'timestamp' => $page->getTimestamp(),
					'page' => $page,
					'collective' => $collective
				];
			}

			// again sort result and slice to the max amount
			usort($results, function (array $a, array $b): int {
				return $b['timestamp'] - $a['timestamp'];
			});
			$results = array_slice($results, 0, self::MAX_ITEMS);
		}

		$items = [];
		foreach ($results as $result) {
			/* @var array{timestamp: int, page: PageInfo, collective: CollectiveInfo} $result */

			$pathParts = [$result['collective']->getName()];
			if ($result['page']->getFilePath() !== '') {
				$pathParts = array_merge($pathParts, explode('/', $result['page']->getFilePath()));
			}
			if ($result['page']->getFileName() !== 'Readme.md') {
				$pathParts[] = $result['page']->getTitle();
			}

			$iconData = $result['page']->getEmoji()
				? $this->getEmojiAvatar($result['page']->getEmoji())
				: $this->getEmojiAvatar('🗒');
				//: $this->getFallbackDataIcon();

			$items[] = new WidgetItem(
				$result['page']->getTitle(),
				$result['collective']->getName(),
				$this->urlGenerator->linkToRoute('collectives.start.indexPath', ['path' => implode('/', $pathParts)]),
				'data:image/svg+xml;base64,' . base64_encode($iconData),
				(string)$result['timestamp']
			);
		}

		return new WidgetItems($items, $this->l10n->t('Add a collective'));
	}

	protected function getFallbackDataIcon(): string {
		// currently unused. Was an attempt to use the text icon which is also the fallback
		// in Collectives itself. Probably because it is not really square, it is being
		// rendered too dominant, compared to regular emojis
		return '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
<svg fill="#fffc" width="14" height="14" viewBox="0 0 18 25.5" xmlns="http://www.w3.org/2000/svg"><path d="M15.8,0H2.3C1,0,0,1,0,2.3v21c0,1.2,1,2.3,2.3,2.3h13.5c1.2,0,2.3-1,2.3-2.3v-21C18,1,17,0,15.8,0z M10.1,20.6H3.4v-2.2h6.8 V20.6z M14.6,16.1H3.4v-2.2h11.2V16.1z M14.6,11.6H3.4V9.4h11.2V11.6z M14.6,7.1H3.4V4.9h11.2V7.1z"></path></svg>';
	}

	public function getReloadInterval(): int {
		return self::REFRESH_INTERVAL_IN_SECS;
	}

	public function getId(): string {
		return 'collectives-recent-pages';
	}

	public function getTitle(): string {
		return $this->l10n->t('Recent pages');
	}

	public function getOrder(): int {
		return 6;
	}

	public function getIconClass(): string {
		return 'icon-collectives';
	}

	public function getUrl(): ?string {
		return $this->urlGenerator->linkToRoute('collectives.collective.index');
	}

	public function load(): void {
	}

	/**
	 * shamelessly copied from @nickvergessen​'s work at Talk
	 * @see https://github.com/nextcloud/spreed/blob/1e5c84ac14fbd1840c970ee7759e7bbdfbcba1a2/lib/Service/AvatarService.php#L174-L192
	 */
	private string $svgTemplate = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
		<svg width="512" height="512" version="1.1" viewBox="0 0 500 500" xmlns="http://www.w3.org/2000/svg">
			<rect width="100%" height="100%" fill="#{fill}"></rect>
			<text x="50%" y="330" style="font-size:240px;font-family:{font};text-anchor:middle;">{letter}</text>
		</svg>';

	/**
	 * shamelessly copied from @nickvergessen​'s work at Talk
	 * @see https://github.com/nextcloud/spreed/blob/1e5c84ac14fbd1840c970ee7759e7bbdfbcba1a2/lib/Service/AvatarService.php#L240-L264
	 */
	protected function getEmojiAvatar(string $emoji, string $fillColor = '00000000'): string {
		return str_replace([
			'{letter}',
			'{fill}',
			'{font}',
		], [
			$emoji,
			$fillColor,
			implode(',', [
				"'Segoe UI'",
				'Roboto',
				'Oxygen-Sans',
				'Cantarell',
				'Ubuntu',
				"'Helvetica Neue'",
				'Arial',
				'sans-serif',
				"'Noto Color Emoji'",
				"'Apple Color Emoji'",
				"'Segoe UI Emoji'",
				"'Segoe UI Symbol'",
				"'Noto Sans'",
			]),
		], $this->svgTemplate);
	}
}
