<?php

namespace Unit\Service;

use OCA\Wiki\Fs\PageMapper;
use OCA\Wiki\Model\Page;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCA\Wiki\Service\PageService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Files\AlreadyExistsException;
use PHPUnit\Framework\TestCase;

class PageServiceTest extends TestCase {
	private $mapper;
	private $service;
	private $userId = 'jane';

	private $page;
	private $pageId = 2;
	private $pageTitle = 'title';

	protected function setUp(): void {
		$this->mapper = $this->getMockBuilder(PageMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->page = new Page();
		$this->page->setId($this->pageId);
		$this->page->setTitle($this->pageTitle);

		$this->service = new PageService($this->mapper);
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

	public function testCreate(): void {
		$newPage = new Page();
		$newPage->setTitle($this->pageTitle);

		$this->mapper->expects($this->once())
			->method('create')
			->with($this->equalTo($newPage))
			->willReturn($this->page);

		$newPage = $this->service->create($this->pageTitle, $this->userId);

		$this->assertEquals($this->page, $newPage);
	}

	public function testRename(): void {
		$this->mapper->expects($this->once())
			->method('find')
			->with($this->equalTo($this->pageId))
			->willReturn($this->page);

		// New values for page
		$renamedPageTitle = 'new_title2';

		// Renamed page
		$renamedPage = new Page();
		$renamedPage->setId($this->pageId);
		$renamedPage->setTitle($renamedPageTitle);
		$this->mapper->expects($this->once())
			->method('rename')
			->with($this->equalTo($renamedPage))
			->willReturn($renamedPage);

		$result = $this->service->rename($this->pageId, $renamedPageTitle, $this->userId);

		$this->assertEquals($renamedPage, $result);
	}
}
