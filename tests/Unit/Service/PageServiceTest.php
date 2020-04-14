<?php

namespace Unit\Db;

use OCA\Wiki\Db\Page;
use OCA\Wiki\Fs\PageMapper;
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
	private $pageContent = 'content';

	protected function setUp(): void {
		$this->mapper = $this->getMockBuilder(PageMapper::class)
			->disableOriginalConstructor()
			->getMock();

		$this->page = new Page();
		$this->page->setId($this->pageId);
		$this->page->setTitle($this->pageTitle);
		$this->page->setContent($this->pageContent);

		$this->service = new PageService($this->mapper);
	}

	/**
	 * @expectedException \OCA\Wiki\Service\NotFoundException
	 */
	public function testHandleExceptionDoesNotExistException(): void {
		$this->service->handleException(new DoesNotExistException('msg'));
	}

	/**
	 * @expectedException \OCA\Wiki\Service\NotFoundException
	 */
	public function testHandleExceptionMultipleObjectsReturnedException(): void {
		$this->service->handleException(new MultipleObjectsReturnedException('msg'));
	}

	/**
	 * @expectedException \OCA\Wiki\Service\NotFoundException
	 */
	public function testHandleExceptionAlreadyExistsException(): void {
		$this->service->handleException(new AlreadyExistsException('msg'));
	}

	/**
	 * @expectedException \OCA\Wiki\Service\NotFoundException
	 */
	public function testHandleExceptionPageDoesNotExistException(): void {
		$this->service->handleException(new PageDoesNotExistException('msg'));
	}

	public function testCreate(): void {
		$newPage = new Page();
		$newPage->setTitle($this->pageTitle);
		$newPage->setContent($this->pageContent);

		$this->mapper->expects($this->once())
			->method('insert')
			->with($this->equalTo($newPage))
			->willReturn($this->page);

		$newPage = $this->service->create($this->pageTitle, $this->pageContent, $this->userId);

		$this->assertEquals($this->page, $newPage);
	}

	public function testUpdate(): void {
		$this->mapper->expects($this->once())
			->method('find')
			->with($this->equalTo($this->pageId))
			->willReturn($this->page);

		// New values for page
		$updatedPageTitle = 'new_title2';
		$updatedPageContent = 'new_content';

		// Updated page
		$updatedPage = new Page();
		$updatedPage->setId($this->pageId);
		$updatedPage->setTitle($updatedPageTitle);
		$updatedPage->setContent($updatedPageContent);
		$this->mapper->expects($this->once())
			->method('update')
			->with($this->equalTo($updatedPage))
			->willReturn($updatedPage);

		$result = $this->service->update($this->pageId, $updatedPageTitle, $updatedPageContent, $this->userId);

		$this->assertEquals($updatedPage, $result);
	}
}
