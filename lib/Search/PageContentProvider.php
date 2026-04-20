<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Search;

use Exception;
use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\PageInfo;
use OCA\Collectives\Search\FileSearch\FileSearchException;
use OCA\Collectives\Search\FileSearch\Tokenizer\WordTokenizer;
use OCA\Collectives\Service\CollectiveHelper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\PageService;
use OCA\Collectives\Service\SearchService;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;
use Psr\Log\LoggerInterface;

class PageContentProvider implements IProvider {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly IURLGenerator $urlGenerator,
		private readonly CollectiveHelper $collectiveHelper,
		private readonly CollectiveService $collectiveService,
		private readonly PageService $pageService,
		private readonly SearchService $indexedSearchService,
		private readonly LoggerInterface $logger,
		private readonly IAppManager $appManager,
	) {
	}

	public function getId(): string {
		return 'collectives-page-content';
	}

	public function getName(): string {
		return $this->l10n->t('Collectives - Page content');
	}

	public function getOrder(string $route, array $routeParameters): int {
		if ($route === 'collectives.start.index') {
			return -1;
		}
		return 4;
	}

	/**
	 * @throws FileSearchException
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult {
		if ($this->appManager->isEnabledForUser('circles', $user)) {
			$collectives = $this->collectiveHelper->getCollectivesForUser($user->getUID(), false, false);
		} else {
			$collectives = [];
		}

		$collectiveMap = [];
		$pages = [];
		foreach ($collectives as $collective) {
			try {
				$collectiveRoot = $this->pageService->getCollectiveFolder($collective->getId(), $user->getUID());
				$results = $this->indexedSearchService->searchCollective($collective, $query->getTerm());
			} catch (FileSearchException|NotFoundException $e) {
				$this->logger->warning('Collectives file content search failed.', [
					'error' => $e->getMessage(),
					'trace' => $e->getTraceAsString()
				]);
				continue;
			}

			foreach ($results as $fileData) {
				$fileId = $fileData['file_id'];
				$fileEntry = $collectiveRoot->getFirstNodeById($fileId);
				if (empty($fileEntry)) {
					continue;
				}

				$pages[$fileId] = ['file' => $fileEntry, 'matched_term' => $fileData['matched_term']];
				$collectiveMap[$fileId] = $collective;
			}
		}

		$pageData = $pages;
		$pages = array_map(fn ($p) => $p['file'], $pages);
		$pages = $this->indexedSearchService->rankByBigrams($query->getTerm(), $pages);

		$pageSearchResults = [];
		foreach ($pages as $page) {
			$collective = $collectiveMap[$page->getId()];
			$pageInfo = $this->getPageInfo($collective, $page, $user->getUID());
			if (!$pageInfo) {
				continue;
			}

			$descriptionSuffix = $pageInfo->getFilePathString()
				? ' - ' . $pageInfo->getFilePathString()
				: ' - ' . $pageInfo->getTitle();
			$description = $this->l10n->t('In collective %1$s', [$this->collectiveService->getCollectiveNameWithEmoji($collective)])
				. $descriptionSuffix;

			$content = $page->getContent();
			$normalizedContent = WordTokenizer::normalize($content);

			$pos = mb_stripos($normalizedContent, $pageData[$page->getId()]['matched_term'] ?? $query->getTerm());
			$pos = $pos !== false ? $pos : 0;

			$pageSearchResults[] = new SearchResultEntry(
				'',
				mb_substr($normalizedContent, $pos, 200),
				$description,
				$this->urlGenerator->linkToRouteAbsolute('collectives.start.index') . $this->pageService->getPageLink($collective->getUrlPath(), $pageInfo),
				'icon-collectives-page'
			);
		}

		return SearchResult::complete(
			$this->getName(),
			$pageSearchResults
		);
	}

	private function getPageInfo(Collective $collective, File $file, string $userId): ?PageInfo {
		try {
			return $this->pageService->findByFile($collective->getId(), $file, $userId);
		} catch (Exception) {
			return null;
		}
	}
}
