<?php

namespace Unit\Service;

use OCA\Wiki\Fs\NodeHelper;
use OCA\Wiki\Model\Page;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCA\Wiki\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private $helper;
	private $service;
	private $userId = 'jane';

	private $page;
	private $pageId = 2;
	private $pageTitle = 'title';

	protected function setUp(): void {
		$this->helper = $this->getMockBuilder(NodeHelper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->page = new Page();
		$this->page->setId($this->pageId);
		$this->page->setTitle($this->pageTitle);

		$this->service = new PageService($this->helper);
	}

	public function testHandleExceptionDoesNotExistException(): void {
		$this->expectException(\OCA\Wiki\Service\NotFoundException::class);
		$this->service->handleException(new DoesNotExistException('msg'));
	}

	public function testHandleExceptionMultipleObjectsReturnedException(): void {
		$this->expectException(\OCA\Wiki\Service\NotFoundException::class);
		$this->service->handleException(new MultipleObjectsReturnedException('msg'));
	}

	public function testHandleExceptionAlreadyExistsException(): void {
		$this->expectException(\OCA\Wiki\Service\NotFoundException::class);
		$this->service->handleException(new AlreadyExistsException('msg'));
	}

	public function testHandleExceptionPageDoesNotExistException(): void {
		$this->expectException(\OCA\Wiki\Service\NotFoundException::class);
		$this->service->handleException(new PageDoesNotExistException('msg'));
	}

	public function testHandleExceptionOtherException(): void {
		$this->expectException(\RuntimeException::class);
		$this->service->handleException(new \RuntimeException('msg'));
	}
}
