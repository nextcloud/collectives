<?php

namespace OCA\Wiki\Service;

use Exception;
use OCA\Wiki\Db\Page;
//use OCA\Wiki\Db\PageMapper;
use OCA\Wiki\Fs\PageMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;

class PageService {
	private $mapper;

	/**
	 * PageService constructor.
	 *
	 * @param PageMapper $mapper
	 */
	public function __construct(PageMapper $mapper) {
		$this->mapper = $mapper;
	}

	/**
	 * @param string $userId
	 *
	 * @return Page[]
	 */
	public function findAll(string $userId): array {
		return $this->mapper->findAll($userId);
	}

	/**
	 * @param $e
	 *
	 * @throws NotFoundException
	 */
	public function handleException ($e) {
		if ($e instanceof DoesNotExistException ||
		    $e instanceof MultipleObjectsReturnedException ||
			$e instanceof AlreadyExistsException ||
			$e instanceof PageDoesNotExistException) {
			throw new NotFoundException($e->getMessage());
		} else {
			throw $e;
		}
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 * @throws NotFoundException
	 */
	public function find(int $id, string $userId): Page {
		try {
			return $this->mapper->find($id, $userId);
		// in order to be able to plug in different storage backends like files
		// for instance it is a good idea to turn storage related exceptions
		// into service related exceptions so controllers and service users
		// have to deal with only one type of exception
		} catch(Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param string $title
	 * @param string $content
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function create(string $title, string $content, string $userId): Page {
		$page = new Page();
		$page->setTitle($title);
		$page->setContent($content);
		$page->setUserId($userId);
		return $this->mapper->insert($page);
	}

	/**
	 * @param int    $id
	 * @param string $title
	 * @param string $content
	 * @param string $userId
	 *
	 * @return Page
	 * @throws NotFoundException
	 */
	public function update(int $id, string $title, string $content, string $userId): Page {
		try {
			$page = $this->mapper->find($id, $userId);
			$page->setId($id);
			$page->setTitle($title);
			$page->setContent($content);
			$page->setUserId($userId);
			return $this->mapper->update($page);
		} catch(Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 * @throws NotFoundException
	 */
	public function delete(int $id, string $userId) {
		try {
			$page = $this->mapper->find($id, $userId);
			$this->mapper->delete($page);
			return $page;
		} catch(Exception $e) {
			$this->handleException($e);
		}
	}
}
