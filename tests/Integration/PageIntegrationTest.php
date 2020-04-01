<?php

namespace OCA\Wiki\Tests\Integration\Controller;

use OCA\Wiki\Controller\PageController;
use OCA\Wiki\Db\Page;
use OCA\Wiki\Fs\PageMapper;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\App;
use OCP\Files\AlreadyExistsException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;


/**
 * This test shows how to make a small Integration PageControllerTest. Query your class
 * directly from the container, only pass in mocks if needed and run your tests
 * against the database
 */
class PageIntegrationTest extends TestCase {

    private $controller;
    private $mapper;
    private $userId = 'jane';

    public function setUp(): void {
        $app = new App('wiki');
        $container = $app->getContainer();

        // only replace the user id
        $container->registerService('userId', function() {
            return $this->userId;
        });

        // we do not care about the request but the controller needs it
        $container->registerService(IRequest::class, function() {
            return $this->createMock(IRequest::class);
        });

        $this->controller = $container->query(PageController::class);
        $this->mapper = $container->query(PageMapper::class);
    }

    public function testAppInstalled(): void {
        $appManager = $this->controller->query('OCP\App\IAppManager');
        $this->assertTrue($appManager->isInstalled('wiki'));
    }

    public function testUpdatePage(): void {
        // create a new page that should be updated
		$page = new Page();
		$page->setTitle('titleasdf23abadb');
		$page->setContent('content');
		$page->setUserId($this->userId);
        $page = $this->mapper->insert($page);
        var_dump($page);

        $page->setTitle('new_title');
        $page->setContent('new_content');

        try {
			$page = $this->mapper->update($page);
		} catch (PageDoesNotExistException $e) {
			$this->mapper->delete($page);
		}

        $this->assertEquals($this->mapper->find($page->getId()->getTitle()), 'new_title');

        // clean up
        $this->mapper->delete($page);
    }
}
