<?php

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\Collective;
use OCA\Collectives\Model\PageFile;
use OCA\Collectives\Service\PageService;

trait PageActions {
	// TODO: make all of these private once we are on PHP 8.0 only
	abstract protected function getUserId(): string
	abstract protected function getCollective(): Collective
	abstract protected function decoratePage(PageFile)
	abstract protected function getService(): PageService
	abstract protected function checkEditPermissions()

	use ErrorHelper;

	/**
	 *
	 * @return DataResponse
	 */
	protected function pageIndex(): DataResponse {
		return $this->handleErrorResponse(function () use ($collectiveId): array {
			$pages = $this->getService()->findAll($this->getUserId(), $this->getCollective());
			foreach ($pages as $page) {
				$this->decoratePage($page);
			}
			return [
				"data" => $pages
			];
		}, $this->logger);
	}

	/**
	 *
	 * @param int    $parentId
	 * @param int    $id
	 *
	 * @return DataResponse
	 */
	protected function getPage(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$page = $this->getService()->find($this->getUserId(), $this->getCollective(), $parentId, $id);
			$this->decoratePage($page);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 *
	 * @param int    $parentId
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	protected function createPage(int $parentId, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $title): array {
			$this->checkEditPermissions();
			$page = $this->getService()->create($this->getUserId(), $this->getCollective(), $parentId, $title);
			$this->decoratePage($page);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 *
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	protected function touchPage(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$this->checkEditPermissions();
			$page = $this->getService()->touch($this->getUserId(), $this->getCollective(), $parentId, $id);
			$this->decoratePage($page);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 *
	 * @param int    $parentId
	 * @param int    $id
	 * @param string $title
	 *
	 * @return DataResponse
	 */
	public function renamePage(int $parentId, int $id, string $title): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id, $title): array {
			$this->checkEditPermissions();
			$page = $this->getService()->rename($this->getUserId(), $this->getCollective(), $parentId, $id, $title);
			$this->decoratePage($page);
			return [
				"data" => $page
			];
		}, $this->logger);
	}

	/**
	 *
	 * @param int $parentId
	 * @param int $id
	 *
	 * @return DataResponse
	 */
	public function deletePage(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$this->checkEditPermissions();
			$page = $this->getService()->delete($this->getShare()->getOwner(), $this->getCollective(), $parentId, $id);
			$this->decoratePage($page);
			return [
				"data" => $page
			];
		}, $this->logger);
	}


	/**
	 *
	 * @param int    $parentId
	 * @param int    $id
	 *
	 * @return DataResponse
	 */
	public function getPageBacklinks(int $parentId, int $id): DataResponse {
		return $this->handleErrorResponse(function () use ($parentId, $id): array {
			$backlinks = $this->getService()->getBacklinks($this->getShare()->getOwner(), $this->getCollective(), $parentId, $id);
			return [
				"data" => $backlinks
			];
		}, $this->logger);
	}
}
