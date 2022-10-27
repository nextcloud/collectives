<?php

declare(strict_types=1);

namespace OCA\Collectives\Service;

use OCA\Collectives\Db\CollectiveMapper;
use OCA\Collectives\Db\CollectiveUserSettings;
use OCA\Collectives\Db\CollectiveUserSettingsMapper;
use OCP\DB\Exception;

class CollectiveUserSettingsService {
	private CollectiveUserSettingsMapper $collectiveUserSettingsMapper;
	private CollectiveMapper $collectiveMapper;

	public function __construct(CollectiveUserSettingsMapper $collectiveUserSettingsMapper,
								CollectiveMapper $collectiveMapper) {
		$this->collectiveUserSettingsMapper = $collectiveUserSettingsMapper;
		$this->collectiveMapper = $collectiveMapper;
	}

	/**
	 * @param int    $collectiveId
	 * @param string $userId
	 * @param int    $pageOrder
	 *
	 * @throws MissingDependencyException
	 * @throws NotFoundException
	 * @throws NotPermittedException
	 */
	public function setPageOrder(int $collectiveId, string $userId, int $pageOrder): void {
		if (null === $this->collectiveMapper->findByIdAndUser($collectiveId, $userId)) {
			throw new NotFoundException('Collective not found: ' . $collectiveId);
		}

		$settings = $this->collectiveUserSettingsMapper->findByCollectiveAndUser($collectiveId, $userId);
		if ($settings === null) {
			$settings = new CollectiveUserSettings();
			$settings->setCollectiveId($collectiveId);
			$settings->setUserId($userId);
		}

		try {
			$settings->setPageOrder($pageOrder);
			$this->collectiveUserSettingsMapper->insertOrUpdate($settings);
		} catch (Exception | \RuntimeException $e) {
			throw new NotPermittedException($e->getMessage(), 0, $e);
		}
	}
}
