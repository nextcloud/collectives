<?php

namespace OCA\Wiki\Service;

use Exception;
use OCA\Wiki\Fs\PageMapper;
use OCA\Wiki\Model\Page;
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
		}

		throw $e;
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
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
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function create(string $title, string $userId): Page {
		$page = new Page();
		$page->setTitle($title);
		return $this->mapper->create($page, $userId);
	}

	/**
	 * @param int    $id
	 * @param string $title
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function rename(int $id, string $title, string $userId): Page {
		try {
			$page = $this->mapper->find($id, $userId);
			$page->setTitle($title);
			return $this->mapper->rename($page, $userId);
		} catch(Exception $e) {
			$this->handleException($e);
		}
	}

	/**
	 * @param int    $id
	 * @param string $userId
	 *
	 * @return Page
	 */
	public function delete(int $id, string $userId): Page {
		try {
			$page = $this->mapper->find($id, $userId);
			$this->mapper->delete($page, $userId);
			return $page;
		} catch(Exception $e) {
			$this->handleException($e);
		}
	}
}
