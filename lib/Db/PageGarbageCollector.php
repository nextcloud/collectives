<?php

namespace OCA\Collectives\Db;

use OCA\Collectives\Mount\CollectiveFolderManager;

class PageGarbageCollector {
	/** @var PageMapper */
	private $pageMapper;

	/** @var CollectiveFolderManager */
	private $folderManager;

	/**
	 * PageGarbageCollector constructor.
	 *
	 * @param PageMapper $pageMapper
	 */
	public function __construct(PageMapper $pageMapper,
								CollectiveFolderManager $folderManager) {
		$this->pageMapper = $pageMapper;
		$this->folderManager = $folderManager;
	}

	/**
	 * @return int
	 */
	public function purgeObsoletePages(): int {
		$purgeCount = 0;
		$rootFolder = $this->folderManager->getRootFolder();
		foreach ($this->pageMapper->getAll() as $page) {
			if (empty($rootFolder->getById($page->getFileId()))) {
				$purgeCount++;
				$this->pageMapper->delete($page);
			}
		}

		return $purgeCount;
	}
}
