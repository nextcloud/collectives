<?php

namespace OCA\Wiki\Tests\Integration\Controller;

use OCA\Wiki\Controller\PageController;
use OCA\Wiki\Db\PageMapper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\App;
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
        $page = $this->service->create('old_title', 'old_content', $this->userId);

        $updatedPage = $this->service->update($page->getId(), 'title', 'content', $this->userId);

        $this->assertEquals($updatedPage, $this->service->find($page->getId(), $this-userId));

        // clean up
        $this->service->delete($page->getId(), $this->userId);
    }
}
