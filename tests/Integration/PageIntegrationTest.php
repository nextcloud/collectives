<?php

namespace OCA\Wiki\Tests\Integration;

use OC\Files\Config\UserMountCache;
use OCA\Wiki\Db\Page;
use OCA\Wiki\Fs\PageMapper;
use OCA\Wiki\Service\PageDoesNotExistException;
use OCP\AppFramework\App;
use PHPUnit\Framework\TestCase;


/**
 * Run tests to create, update and delete a page against the database.
 */
class PageIntegrationTest extends TestCase {

	private $mapper;
	private $provider;
	private $userId = 'jane';

	protected function setUp(): void {
		parent::setUp();
		$app = new App('wiki');
		$container = $app->getContainer();

		// create user and set userId
		\OC::$server->getUserManager()->createUser($this->userId, 'test');
		\OC_User::setUserId($this->userId);
		\OC_Util::setupFS($this->userId);
		\OC::$server->getUserFolder($this->userId);

		// TODO: For some reason, the storage for user is not mounted yet.
		//       This leads to failing integration tests since `$folder->getById()`
		//       always returns an empty array since it depends on
		//       `UserMountCache::getMountsForFileId` which in turn runs
		//       `getMountsForStorageId` which returns an empty list.
		//
		//       The result is that even if a file with the id exists in the
		//       folder (`$folder->getDirectoryListing()[0]->getId()` verifies
		//       that), `$folder->getById($id)` returns nothing.
		//
		//       So my current guess is that we probably have to run some further
		//       initialization function here in order to setup the mount for user
		//       storage.

		// TODO: Remove debugging code after the user storage mount stuff got resolved
		$user = \OC::$server->getUserManager()->get($this->userId);
		$this->mapper = $container->query(PageMapper::class);
		$this->provider = $container->query(UserMountCache::class);
		var_dump($this->provider->getMountsForUser($user));
	}

	protected function tearDown(): void {
		\OC_User::setUserId('');
		$user = \OC::$server->getUserManager()->get($this->userId);
		if ($user !== null) {
			$user->delete();
		}
	}

//	public function testUpdatePage(): void {
//		// create a new page
//		$page = new Page();
//		$page->setTitle('title');
//		$page->setContent('content');
//		$page = $this->mapper->insert($page, $this->userId);
//
//		// update the page
//		$page->setTitle('new_title');
//		$page->setContent('new_content');
//
//		$page = $this->mapper->update($page, $this->userId);
//
//		$this->assertEquals($this->mapper->find($page->getId(), $this->userId)->getTitle(),'new_title');
//		$this->assertEquals($this->mapper->find($page->getId(), $this->userId)->getContent(),'new_content');
//
//		// delete the page
//		$this->mapper->delete($page, $this->userId);
//	}
}
