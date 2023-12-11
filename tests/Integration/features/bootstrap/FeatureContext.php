<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context {
	private string $baseUrl;
	private string $remoteUrl;
	private string $ocsUrl;
	private array $clientOptions;
	private ?ResponseInterface $response = null;
	private ?array $json = null;
	private ?string $currentUser = null;
	private array $cookieJars = [];
	private array $requestTokens = [];
	private array $store = [];

	private const CIRCLE_MEMBER_LEVEL = [
		1 => 'Member',
		4 => 'Moderator',
		8 => 'Admin',
		9 => 'Owner'
	];

	private const PAGE_MODE = [
		0 => 'view',
		1 => 'edit',
	];

	/**
	 * Initializes context.
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 *
	 * @param string $baseUrl
	 * @param string $remoteUrl
	 * @param string $ocsUrl
	 */
	public function __construct(string $baseUrl, string $remoteUrl, string $ocsUrl) {
		$this->baseUrl = $baseUrl;
		$this->remoteUrl = $remoteUrl;
		$this->ocsUrl = $ocsUrl;
		$this->clientOptions = ['verify' => false];
	}

	/**
	 * @When user :user creates collective :collective
	 * @When user :user :fails to create collective :collective
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesCollective(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$formData = new TableNode([['name', $collective]]);
		$this->sendRequest('POST', '/apps/collectives/_api', $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(422);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user sets :type level in collective :collective to :level
	 *
	 * @param string $user
	 * @param string $type
	 * @param string $collective
	 * @param string $level
	 *
	 * @throws GuzzleException
	 */
	public function userUpdatesCollectivePermissionLevel(string $user, string $type, string $collective, string $level): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);

		$intLevel = array_search($level, self::CIRCLE_MEMBER_LEVEL, true);
		if (!$intLevel) {
			throw new \RuntimeException('Could not verify circle member level ' . $level);
		}

		$formData = new TableNode([['level', $intLevel]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/' . $type . 'Level', $formData);
		$this->assertStatusCode(200);

		$this->sendRequest('GET', '/apps/collectives/_api');
		$this->assertCollectiveKeyValue($collective, $type . 'PermissionLevel', $intLevel);
	}

	/**
	 * @When user :user sets pageMode for collective :collective to :mode
	 * @When user :user :fails to set pageMode for collective :collective to :mode
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string      $mode
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userUpdatesCollectivePageMode(string $user, string $collective, string $mode, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);

		$intMode = array_search($mode, self::PAGE_MODE, true);
		if (!$intMode) {
			throw new \RuntimeException('Could not verify page mode ' . $mode);
		}

		$formData = new TableNode([['mode', $intMode]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/pageMode', $formData);

		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);

			$this->sendRequest('GET', '/apps/collectives/_api');
			$this->assertCollectiveKeyValue($collective, 'pageMode', $intMode);
		}
	}

	/**
	 * @When user :user creates page :page with parentPath :parentPath in :collective
	 * @When user :user :fails to create page :page with parentPath :parentPath in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $parentPath
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesPage(string $user, string $page, string $parentPath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page]]);
		$this->sendRequest('POST', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $parentId, $formData);

		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @Then user :user sees collective :collective
	 * @Then user :user sees collective :collective in :trash
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $trash
	 *
	 * @throws GuzzleException
	 */
	public function userSeesCollective(string $user, string $collective, ?string $trash = null): void {
		$this->setCurrentUser($user);
		if ($trash) {
			$this->sendRequest('GET', '/apps/collectives/_api/trash');
		} else {
			$this->sendRequest('GET', '/apps/collectives/_api');
		}
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective);
	}

	/**
	 * @Then user :user sees pagePath :pagePath in :collective
	 * @Then user :user :fails to see pagePath :pagePath in :collective
	 *
	 * @param string      $user
	 * @param string      $pagePath
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userSeesPagePath(string $user, string $pagePath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages');
		$this->assertStatusCode(200);
		if ($fail === 'fails') {
			$this->assertPageByPath($pagePath, true);
		} else {
			$this->assertPageByPath($pagePath);
		}
	}

	/**
	 * @Then user :user doesn't see collective :collective
	 * @Then user :user doesn't see collective :collective in :trash
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $trash
	 *
	 * @throws GuzzleException
	 */
	public function userDoesntSeeCollective(string $user, string $collective, ?string $trash = null): void {
		$this->setCurrentUser($user);
		if ($trash) {
			$this->sendRequest('GET', '/apps/collectives/_api/trash');
		} else {
			$this->sendRequest('GET', '/apps/collectives/_api');
		}
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective, true);
	}

	/**
	 * @Then user :user doesn't see pagePath :pagePath in :collective
	 *
	 * @param string $user
	 * @param string $pagePath
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userDoesntSeePagePath(string $user, string $pagePath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages');
		$this->assertStatusCode(200);
		$this->assertPageByPath($pagePath, true);
	}

	/**
	 * @Then user :user sees attachment :name with mimetype :mimetype for :page in :collective
	 *
	 * @param string $user
	 * @param string $name
	 * @param string $mimetype
	 * @param string $page
	 * @param string $collective
	 *
	 * @return void
	 */
	public function userSeesAttachments(string $user, string $name, string $mimetype, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId . '/attachments');
		$this->assertStatusCode(200);
		$this->assertAttachment($name, $mimetype);
	}

	/**
	 * @Then user :user last edited page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userLastEditedPage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages');
		$this->assertStatusCode(200);
		$this->assertPageLastEditedByUser($page, $user);
	}

	/**
	 * @When user :user trashes collective :collective
	 * @When user :user :fails to trash collective :collective
	 * @When user :user :fails to trash foreign collective :collective with member :member
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $fail
	 * @param string|null $member
	 *
	 * @throws GuzzleException
	 */
	public function userTrashesCollective(string $user, string $collective, ?string $fail = null, ?string $member = null): void {
		$this->setCurrentUser($member ?: $user);
		$collectiveId = $this->collectiveIdByName($collective);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId);
		if ("fails" === $fail) {
			$this->assertStatusCode($member ? 404 : 403);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user deletes collective :collective
	 * @When user :user :fails to delete collective :collective
	 * @When user :user :fails to delete collective :collective with admin :admin
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $fail
	 * @param string|null $admin
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesCollective(string $user, string $collective, ?string $fail = null, ?string $admin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);

		$this->sendRequest('DELETE', '/apps/collectives/_api/trash/' . $collectiveId);
		if ("fails" === $fail) {
			$this->assertStatusCode(404);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user deletes collective+circle :collective
	 * @When user :user :fails to delete collective+circle :collective
	 * @When user :user :fails to delete collective+circle :collective with admin :admin
	 * @When user :user :fails to delete :selfadmin collective+circle :collective
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $fail
	 * @param string|null $admin
	 * @param string|null $selfadmin
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesCollectiveAndCircle(string $user, string $collective, ?string $fail = null, ?string $admin = null, ?string $selfadmin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/collectives/_api/trash/' . $collectiveId . '?circle=1');
		if ("fails" === $fail) {
			$this->assertStatusCode($selfadmin ? 403 : 404);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user restores collective :collective
	 * @When user :user :fails to restore collective :collective
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userRestoresCollective(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->sendRequest('PATCH', '/apps/collectives/_api/trash/' . $collectiveId);
		if ("fails" === $fail) {
			$this->assertStatusCode(404);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user collective :collective property :property is :value
	 *
	 * @param string $user
	 * @param string $collective
	 * @param string $property
	 * @param string $value
	 *
	 * @throws GuzzleException
	 */
	public function userCollectiveProperty(string $user, string $collective, string $property, string $value): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/collectives/_api');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$this->assertCollectiveKeyValue($collective, $property, $value);
	}

	/**
	 * @When user :user sets userSetting :userSetting for collective :collective to :value
	 *
	 * @param string $user
	 * @param string $userSetting
	 * @param string $collective
	 * @param string $value
	 *
	 * @throws GuzzleException
	 */
	public function userSetsCollectiveUserSetting(string $user, string $userSetting, string $collective, string $value): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$formData = new TableNode([[$userSetting, $value]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_userSettings/' . $userSetting, $formData);
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to set userSetting for collective');
		}

		$this->userCollectiveProperty($user, $collective, 'user' . ucfirst($userSetting), $value);
	}

	/**
	 * @When user :user trashes page :page in :collective
	 * @When user :user :fails to trash page :page in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userTrashesPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user restores page :page from trash in :collective
	 * @When user :user :fails to restore page :page from trash in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userRestoresPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ("fails" === $fail) {
			$this->sendRequest('PATCH', '/apps/collectives/_api/' . $collectiveId . '/_pages/trash/' . 1);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page);
			$this->sendRequest('PATCH', '/apps/collectives/_api/' . $collectiveId . '/_pages/trash/' . $pageId);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user deletes page :page from trash in :collective
	 * @When user :user :fails to delete page :page from trash in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ("fails" === $fail) {
			$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/_pages/trash/' . 1);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page);
			$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/_pages/trash/' . $pageId);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user touches page :page in :collective
	 * @When user :user :fails to touch page :page in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userTouchesPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId . '/touch');
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user moves page :page to :newtitle with parentPath :parentPath in :collective
	 * @When user :user :fails to move page :page to :newtitle with parentPath :parentPath in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $newtitle
	 * @param string      $parentPath
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userMovesPage(string $user, string $page, string $newtitle, string $parentPath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([['parentId', $parentId], ['title', $newtitle]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId, $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user copies page :page to :newtitle with parentPath :parentPath in :collective
	 * @When user :user :fails to copy page :page to :newtitle with parentPath :parentPath in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $newtitle
	 * @param string      $parentPath
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userCopiesPage(string $user, string $page, string $newtitle, string $parentPath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([
			['parentId', $parentId],
			['title', $newtitle],
			['copy', true],
		]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId, $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user moves page :page from collective :oldCollectiveId to collective :newCollectiveId
	 * @When user :user moves page :page from collective :oldCollectiveId to collective :newCollectiveId with parentPath :parentPath
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $oldCollective
	 * @param string      $newCollective
	 * @param string|null $parentPath
	 *
	 * @throws GuzzleException
	 */
	public function userMovesPageToCollective(string $user, string $page, string $oldCollective, string $newCollective, ?string $parentPath = null): void {
		$this->setCurrentUser($user);
		$oldCollectiveId = $this->collectiveIdByName($oldCollective);
		$newCollectiveId = $this->collectiveIdByName($newCollective);
		$pageId = $this->pageIdByName($oldCollectiveId, $page);
		if ($parentPath) {
			$newParentId = $this->getParentId($newCollectiveId, $parentPath);
			$formData = new TableNode([['parentId', $newParentId]]);
		} else {
			$formData = null;
		}
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $oldCollectiveId . '/_pages/' . $pageId . '/to/' . $newCollectiveId, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user copies page :page from collective :oldCollectiveId to collective :newCollectiveId
	 * @When user :user copies page :page from collective :oldCollectiveId to collective :newCollectiveId with parentPath :parentPath
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $oldCollective
	 * @param string      $newCollective
	 * @param string|null $parentPath
	 *
	 * @throws GuzzleException
	 */
	public function userCopiesPageToCollective(string $user, string $page, string $oldCollective, string $newCollective, ?string $parentPath = null): void {
		$this->setCurrentUser($user);
		$oldCollectiveId = $this->collectiveIdByName($oldCollective);
		$newCollectiveId = $this->collectiveIdByName($newCollective);
		$pageId = $this->pageIdByName($oldCollectiveId, $page);
		if ($parentPath) {
			$newParentId = $this->getParentId($newCollectiveId, $parentPath);
			$formData = new TableNode([['parentId', $newParentId], ['copy', true]]);
		} else {
			$formData = new TableNode([['copy', true]]);
		}
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $oldCollectiveId . '/_pages/' . $pageId . '/to/' . $newCollectiveId, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user sets emoji for page :page to :emoji in :collective
	 * @When user :user :fails to set emoji for page :page to :emoji in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $emoji
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPageEmoji(string $user, string $page, string $emoji, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId . '/emoji', $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'emoji', $emoji);
		}
	}

	/**
	 * @When user :user sets subpageOrder for page :page to :subpageOrder in :collective
	 * @When user :user :fails to set subpageOrder for page :page to :subpageOrder in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $subpageOrder
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPageSubpageOrder(string $user, string $page, string $subpageOrder, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$formData = new TableNode([['subpageOrder', $subpageOrder]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId . '/subpageOrder', $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'subpageOrder', json_decode($subpageOrder, true, 512, JSON_THROW_ON_ERROR));
		}
	}

	/**
	 * @When user :user gets setting :key with value :value
	 *
	 * @param string $user
	 * @param string $key
	 * @param string $value
	 *
	 * @throws GuzzleException
	 */
	public function userGetsSetting(string $user, string $key, string $value): void {
		$this->setCurrentUser($user);

		$this->sendOcsRequest('GET', '/apps/collectives/api/v1.0/settings/user/' . $key);
		$this->assertStatusCode(200);

		$jsonBody = $this->getJson();
		Assert::assertEquals($value, $jsonBody['ocs']['data']);
	}

	/**
	 * @When user :user sets setting :key to value :value
	 * @When user :user :fails to set setting :key to value :value
	 *
	 * @param string      $user
	 * @param string      $key
	 * @param string      $value
	 * @param string|null $fails
	 *
	 * @throws GuzzleException
	 */
	public function userSetsSetting(string $user, string $key, string $value, ?string $fails = null): void {
		$this->setCurrentUser($user);

		$data = new TableNode([
			['key', $key],
			['value', $value],
		]);
		$this->sendOcsRequest('POST', '/apps/collectives/api/v1.0/settings/user', $data);
		if ($fails !== "fails") {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(400);
		}
	}

	/**
	 * @When user :user joins circle :name with owner :owner
	 * @When user :user joins circle :name with owner :owner with level :level
	 *
	 * @param string      $user
	 * @param string      $name
	 * @param string      $owner
	 * @param string|null $level
	 *
	 * @throws GuzzleException
	 */
	public function userJoinsCircle(string $user, string $name, string $owner, ?string $level = null): void {
		$this->setCurrentUser($owner);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);

		$data = new TableNode([
			['userId', $user],
			['type', 1],
		]);

		$this->sendOcsRequest('POST', '/apps/circles/circles/' . $circleId . '/members', $data);
		$this->assertStatusCode([200, 400]);

		if ($level) {
			$memberId = $this->circleMemberByUser($circleId, $user);
			Assert::assertNotNull($memberId);
			$data = new TableNode([
				['level', $level],
			]);

			$this->sendOcsRequest('PUT', '/apps/circles/circles/' . $circleId . '/members/' . $memberId . '/level', $data);
			$this->assertStatusCode([200, 400]);
		}
	}

	/**
	 * @When user :user leaves circle :name with owner :owner
	 */
	public function userLeavesCircle(string $user, string $name, string $owner): void {
		$this->setCurrentUser($owner);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
		$memberId = $this->circleMemberIdByName($circleId, $user);
		Assert::assertNotNull($memberId);

		$this->sendOcsRequest('DELETE', '/apps/circles/circles/' . $circleId . '/members/' . $memberId);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user is member of circle :name
	 *
	 * @param string $user
	 * @param string $name
	 *
	 * @throws GuzzleException
	 */
	public function userIsMemberOfCircle(string $user, string $name): void {
		$this->setCurrentUser($user);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
	}

	/**
	 * @When user :user deletes circle :name
	 * @When user :user :fails to delete circle :name
	 *
	 * @param string      $user
	 * @param string      $name
	 * @param string|null $fails
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesCircle(string $user, string $name, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
		$this->sendOcsRequest('DELETE', '/apps/circles/circles/' . $circleId);
		if ($fails !== "fails") {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(400);
		}
	}

	/**
	 * @When app :appId is :status
	 *
	 * @param string $appId
	 * @param string $status
	 *
	 * @throws GuzzleException
	 */
	public function toggleApp(string $appId, string $status): void {
		$this->setCurrentUser('admin');

		$jsonData = ['appIds' => [$appId]];

		if ($status === 'enabled') {
			$this->sendRequest('POST', '/settings/apps/enable', null, $jsonData);
		} elseif ($status === 'disabled') {
			$this->sendRequest('POST', '/settings/apps/disable', null, $jsonData);
		} else {
			throw new RuntimeException('Unknown app status: ' . $status);
		}
	}

	/**
	 * @When user :user has quota :quota
	 *
	 * @param string $user
	 * @param string $quota
	 *
	 * @throws GuzzleException
	 */
	public function setUserQuota(string $user, string $quota): void {
		$this->setCurrentUser('admin');
		$data = new TableNode([
			['key', 'quota'],
			['value', $quota],
		]);
		$this->sendOcsRequest('PUT', '/cloud/users/' . $user, $data);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user creates public share for :collective
	 * @When user :user :fails to create public share for :collective
	 *
	 * @param string $user
	 * @param string $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesPublicShare(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('POST', '/apps/collectives/_api/' . $collectiveId . '/share');
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$jsonBody = $this->getJson();
			Assert::assertNotEmpty($jsonBody['data']['shareToken']);
		}
	}

	/**
	 * @When user :user creates public page share for page :page in :collective
	 * @When user :user :fails to create public page share for page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesPublicPageShare(string $user, string $collective, string $page, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('POST', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId . '/share');
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$jsonBody = $this->getJson();
			Assert::assertNotEmpty($jsonBody['data']['shareToken']);
		}
	}

	/**
	 * @When user :user sets editing permissions for collective share :collective
	 *
	 * @param string $user
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPublicShareEditPermissions(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$formData = new TableNode([['editable', true]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/share/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user sets editing permissions for page share :page in collective :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPublicFileShareEditPermissions(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$token = $this->getShareToken($collectiveId, $pageId);
		$formData = new TableNode([['editable', true]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageId . '/share/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user unsets editing permissions for collective :collective
	 *
	 * @param string $user
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userUnsetsPublicShareEditPermissions(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$formData = new TableNode([['editable', false]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/share/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user unsets editing permissions for page share :page in collective :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userUnsetsPublicFileShareEditPermissions(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$token = $this->getShareToken($collectiveId, $pageId);
		$formData = new TableNode([['editable', false]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/share/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user stores token for public share :collective
	 * @When user :user stores token for public page share :pageShare in collective :collective
	 *
	 * @param string  $user
	 * @param string  $collective
	 * @param ?string $page
	 *
	 * @throws GuzzleException
	 */
	public function userStoresPublicShareToken(string $user, string $collective, ?string $pageShare = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = 0;
		if ($pageShare) {
			$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		}
		$this->store['shareToken'] = $this->getShareToken($collectiveId, $pageShareId);
	}

	/**
	 * @When user :user deletes public share for :collective
	 * @When user :user deletes public page share :pageShare in collective :collective
	 *
	 * @param string  $user
	 * @param string  $collective
	 * @param ?string $pageShare
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesPublicShare(string $user, string $collective, ?string $pageShare = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = 0;
		if ($pageShare) {
			$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		}
		$token = $this->getShareToken($collectiveId, $pageShareId);
		if ($pageShare) {
			$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/_pages/' . $pageShareId . '/share/' . $token);
		} else {
			$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/share/' . $token);
		}
		$this->assertStatusCode(200);
		$this->assertCollectiveLevel($collective, 9);
		$jsonBody = $this->getJson();
		Assert::assertEmpty($jsonBody['data']['shareToken']);
	}

	/**
	 * @When anonymous sees public collective :collective with owner :owner
	 *
	 * @param string      $collective
	 * @param string      $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicCollective(string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token, null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective);
		$this->assertCollectiveLevel($collective, 1);
	}

	/**
	 * @When anonymous sees public page share :page in collective :collective with owner :owner
	 *
	 * @param string $page
	 * @param string $collective
	 * @param string $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicPageShare(string $page, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$token = $this->getShareToken($collectiveId, $pageId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token, null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective);
		$this->assertCollectiveLevel($collective, 1);
	}

	/**
	 * @When anonymous fails to see public share with stored token
	 *
	 * @throws GuzzleException
	 */
	public function anonymousFailsToSeePublicCollective(): void {
		Assert::assertArrayHasKey('shareToken', $this->store);
		Assert::assertNotEmpty($this->store['shareToken']);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $this->store['shareToken'], null, null, [], false);
		$this->assertStatusCode(404);
	}

	/**
	 * @When anonymous sees pagePath :path in public collective :collective with owner :owner
	 *
	 * @param string $path
	 * @param string $collective
	 * @param string $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicCollectivePages(string $path, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path);
	}

	/**
	 * @When anonymous sees pagePath :path in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string $path
	 * @param string $pageShare
	 * @param string $collective
	 * @param string $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicPagePages(string $path, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path);
	}

	/**
	 * @When anonymous doesn't see pagePath :path in public collective :collective with owner :owner
	 *
	 * @param string $path
	 * @param string $collective
	 * @param string $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDoesntSeePublicCollectivePages(string $path, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path, true);
	}

	/**
	 * @When anonymous doesn't see pagePath :path in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string $path
	 * @param string $pageShare
	 * @param string $collective
	 * @param string $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDoesntSeePublicPageSharePages(string $path, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path, true);
	}

	/**
	 * @When anonymous creates page :page with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to create page :page with parentPath :parentPath in public collective :collective with owner :owner
	 *
	 * @param string $page
	 * @param string $parentPath
	 * @param string $collective
	 * @param string $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousCreatesPublicCollectivePage(string $page, string $parentPath, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page]]);
		$this->sendRequest('POST', '/apps/collectives/_api/p/' . $token . '/_pages/' . $parentId, $formData, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous creates page :page with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
	 * @When anonymous :fails to create page :page with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string $page
	 * @param string $parentPath
	 * @param string $pageShare
	 * @param string $collective
	 * @param string $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousCreatesPublicPagePage(string $page, string $parentPath, string $pageShare, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page]]);
		$this->sendRequest('POST', '/apps/collectives/_api/p/' . $token . '/_pages/' . $parentId, $formData, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous moves page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to move page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $newtitle
	 * @param string      $parentPath
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousMovesPage(string $page, string $newtitle, string $parentPath, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([['parentId', $parentId], ['title', $newtitle]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId, $formData, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous moves page :page to :newtitle with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
	 * @When anonymous :fails to move page :page to :newtitle with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $newtitle
	 * @param string      $parentPath
	 * @param string      $pageShare
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousMovesPageInPageShare(string $page, string $newtitle, string $parentPath, string $pageShare, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([['parentId', $parentId], ['title', $newtitle]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId, $formData, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous copies page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to copy page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $newtitle
	 * @param string      $parentPath
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousCopiesPage(string $page, string $newtitle, string $parentPath, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([
			['parentId', $parentId],
			['title', $newtitle],
			['copy', true],
		]);
		$this->sendRequest('PUT', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId, $formData, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous sets emoji for page :page to :emoji in public collective :collective with owner :owner
	 * @When anonymous :fails to set emoji for page :page to :emoji in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $emoji
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSetsPublicCollectivePageEmoji(string $page, string $emoji, string $collective, $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId . '/emoji', $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'emoji', $emoji);
		}
	}

	/**
	 * @When anonymous sets emoji for page :page to :emoji in public page share :pageShare in collective :collective with owner :owner
	 * @When anonymous :fails to set emoji for page :page to :emoji in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $emoji
	 * @param string      $pageShare
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSetsPublicPageSharePageEmoji(string $page, string $emoji, string $pageShare, string $collective, $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId . '/emoji', $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'emoji', $emoji);
		}
	}

	/**
	 * @When anonymous trashes page :page in public collective :collective with owner :owner
	 * @When anonymous :fails to trash page :page in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousTrashesPublicCollectivePage(string $page, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$this->sendRequest('DELETE', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId, null, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous fails to trash page :page in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string $page
	 * @param string $pageShare
	 * @param string $collective
	 * @param string $owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousTrashesPublicPageSharePage(string $page, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$this->sendRequest('DELETE', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId, null, null, [], false);
		$this->assertStatusCode(403);
	}

	/**
	 * @When anonymous restores page :page from trash in public collective :collective with owner :owner
	 * @When anonymous :fails to restore page :page from trash in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousRestoresPublicCollectivePage(string $page, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);

		if ("fails" === $fail) {
			$this->sendRequest('PATCH', '/apps/collectives/_api/p/' . $token . '/_pages/trash/1', null, null, [], false);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page, $token);
			$this->sendRequest('PATCH', '/apps/collectives/_api/p/' . $token . '/_pages/trash/' . $pageId, null, null, [], false);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous deletes page :page from trash in public collective :collective with owner :owner
	 * @When anonymous :fails to delete page :page from trash in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $collective
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDeletesPublicCollectivePage(string $page, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);

		if ("fails" === $fail) {
			$this->sendRequest('DELETE', '/apps/collectives/_api/p/' . $token . '/_pages/trash/1', null, null, [], false);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page, $token);
			$this->sendRequest('DELETE', '/apps/collectives/_api/p/' . $token . '/_pages/trash/' . $pageId, null, null, [], false);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @Then anonymous sees attachment :name with mimetype :mimetype for :page in public collective :collective with owner :owner
	 *
	 * @param string $name
	 * @param string $mimetype
	 * @param string $page
	 * @param string $collective
	 * @param string $owner
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	public function anonymousSeesAttachments(string $name, string $mimetype, string $page, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId . '/attachments');
		$this->assertStatusCode(200);
		$this->assertAttachment($name, $mimetype);
	}

	/**
	 * @Then anonymous sees attachment :name with mimetype :mimetype for :page in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @param string $name
	 * @param string $mimetype
	 * @param string $page
	 * @param string $pageShare
	 * @param string $collective
	 * @param string $owner
	 *
	 * @return void
	 * @throws GuzzleException
	 */
	public function anonymousSeesPageShareAttachments(string $name, string $mimetype, string $page, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages/' . $pageId . '/attachments');
		$this->assertStatusCode(200);
		$this->assertAttachment($name, $mimetype);
	}

	/**
	 * @param string $user
	 *
	 * @return string
	 */
	private function getUserCollectivesPath(string $user): string {
		// Dirty hack to not break it on local dev setup
		$lang = $this->getUserLanguage($user);
		if ($lang === 'de') {
			return 'Kollektive';
		}

		return 'Collectives';
	}
	/**
	 * @When user :user has webdav access to :collective with permissions :permissions
	 *
	 * @param string $collective
	 * @param string $user
	 * @param string $permissions
	 *
	 * @throws GuzzleException
	 */
	public function hasWebdavAccess(string $collective, string $user, string $permissions): void {
		$this->setCurrentUser($user);
		$headers = [
			'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
			'Depth' => 0,
		];
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$xPropfind = $dom->createElementNS('DAV:', 'D:propfind');
		$xProp = $dom->createElement('D:prop');
		$xProp->setAttribute('xmlns:oc', 'http://owncloud.org/ns');
		$xProp->appendChild($dom->createElement('oc:permissions'));
		$dom->appendChild($xPropfind)->appendChild($xProp);
		$body = $dom->saveXML();
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		$this->sendRemoteRequest('PROPFIND', '/dav/files/' . $user . '/' . $userCollectivesPath . '/' . urlencode($collective) . '/', $body, null, $headers);
		$this->assertStatusCode(207);

		// simplexml_load_string() would be better than preg_replace
		$folderPermissions = preg_replace('/.*<oc:permissions>(.*)<\/oc:permissions>.*/sm', '\1', $this->response->getBody()->getContents());

		Assert::assertEquals($permissions, $folderPermissions);
	}

	/**
	 * @param string $user
	 *
	 * @return array
	 * @throws GuzzleException
	 */
	private function listWebdavTrash(string $user): array {
		$this->setCurrentUser($user);
		$headers = [
			'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
			'Depth' => 1,
		];
		$dom = new \DOMDocument('1.0', 'UTF-8');
		$xPropfind = $dom->createElementNS('DAV:', 'D:propfind');
		$xProp = $dom->createElement('D:prop');
		$xProp->setAttribute('xmlns:nc', 'http://nextcloud.org/ns');
		$xProp->appendChild($dom->createElement('nc:trashbin-title'));
		$dom->appendChild($xPropfind)->appendChild($xProp);
		$body = $dom->saveXML();

		$this->sendRemoteRequest('PROPFIND', '/dav/trashbin/' . $user . '/trash/', $body, null, $headers);
		$this->assertStatusCode(207);

		$xml = simplexml_load_string($this->response->getBody()->getContents());
		$entries = [];
		$count = 0;
		foreach ($xml->xpath('//d:href') as $xmlItem) {
			$href = (string)$xmlItem;
			$trashbinTitle = (string)$xmlItem->xpath('//nc:trashbin-title')[$count];
			$entries[] = [
				'href' => $href, 'trashbinTitle' => $trashbinTitle
			];
			$count++;
		}

		return $entries;
	}

	/**
	 * @param string $collective
	 * @param string $filePath
	 * @param string $user
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	private function inWebdavTrash(string $collective, string $filePath, string $user): ?string {
		$webdavTrashEntries = $this->listWebdavTrash($user);
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		foreach ($webdavTrashEntries as $entry) {
			if ($entry['trashbinTitle'] === $userCollectivesPath . '/' . urlencode($collective) . '/' . $filePath) {
				return $entry['href'];
			}
		}

		return null;
	}

	/**
	 * @When user :user uploads attachment :fileName to :page in :collective
	 *
	 * @param string $user
	 * @param string $fileName
	 * @param string $page
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function uploadsAttachment(string $user, string $fileName, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		$attachmentsPath = '/dav/files/' . $user . '/' . $userCollectivesPath . '/' . urlencode($collective) . '/' . '.attachments.' . $pageId;

		$this->sendRemoteRequest('MKCOL', $attachmentsPath);
		$body = fopen('tests/Integration/features/fixtures/' . $fileName, 'rb');
		$this->sendRemoteRequest('PUT', $attachmentsPath . '/' . $fileName, $body);
	}

	/**
	 * @When user :user trashes page :page via webdav in :collective
	 * @When user :user :fails to trash page :page via webdav in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function webdavTrashFile(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		$filePath = '/dav/files/' . $user . '/' . $userCollectivesPath . '/' . urlencode($collective) . '/' . urlencode($page) . '.md';

		$this->sendRemoteRequest('DELETE', $filePath);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(204);
		}
	}

	/**
	 * @When user :user restores page :page from trash via webdav in :collective
	 * @When user :user :fails to restore page :page from trash via webdav in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function webdavRestoreFile(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);

		$href = $this->inWebdavTrash($collective, urlencode($page) . '.md', $user);
		if ("fails" === $fail) {
			Assert::assertNull($href, 'Page found in trash even though not expected: ' . $page);
			return;
		}
		Assert::assertNotNull($href, 'Page not found in trash: ' . $page);

		$hrefSplit = explode('/', $href);
		$trashFilename = end($hrefSplit);
		$filePath = '/dav/trashbin/' . $user . '/trash/' . $trashFilename;

		$headers = ['destination' => $this->remoteUrl . '/dav/trashbin/' . $user . '/restore/' . $trashFilename];
		$this->sendRemoteRequest('MOVE', $filePath, null, null, $headers);
		$this->assertStatusCode(201);
	}

	/**
	 * @When user :user deletes page :page from trash via webdav in :collective
	 * @When user :user :fails to delete page :page from trash via webdav in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function webdavDeleteFile(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);

		$href = $this->inWebdavTrash($collective, urlencode($page) . '.md', $user);
		if ("fails" === $fail) {
			Assert::assertNull($href, 'Page found in trash even though not expected: ' . $page);
			return;
		}
		Assert::assertNotNull($href, 'Page not found in trash: ' . $page);

		$hrefSplit = explode('/', $href);
		$trashFilename = end($hrefSplit);
		$filePath = '/dav/trashbin/' . $user . '/trash/' . $trashFilename;

		$this->sendRemoteRequest('DELETE', $filePath);
		$this->assertStatusCode(204);
	}

	/**
	 * @return array
	 * @throws JsonException
	 */
	private function getJson(): array {
		if (!$this->json) {
			$this->json = json_decode($this->response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
		}
		return $this->json;
	}

	/**
	 * @param string $name
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	private function circleIdByName(string $name): ?string {
		$this->sendOcsRequest('GET', '/apps/circles/circles');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of circles');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data'] as $circle) {
			if ($name === $circle['name']) {
				return $circle['id'];
			}
		}
		return null;
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	private function circleMemberByUser(string $circleId, string $user): ?string {
		$this->sendOcsRequest('GET', '/apps/circles/circles/' . $circleId . '/members');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of members for circle ' . $circleId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data'] as $member) {
			if ($user === $member['userId']) {
				return $member['id'];
			}
		}
		return null;
	}

	/**
	 * @param string $circleId
	 * @param string $userId
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	private function circleMemberIdByName(string $circleId, string $userId): ?string {
		$this->sendOcsRequest('GET', '/apps/circles/circles/' . $circleId . '/members');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of circle members');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data'] as $member) {
			if ($userId === $member['userId']) {
				return $member['id'];
			}
		}
		return null;
	}

	/**
	 * @param string $name
	 * @param bool   $trash
	 *
	 * @return int|null
	 * @throws GuzzleException
	 */
	private function collectiveIdByName(string $name, bool $trash = false): ?int {
		if ($trash) {
			$this->sendRequest('GET', '/apps/collectives/_api/trash');
		} else {
			$this->sendRequest('GET', '/apps/collectives/_api');
		}
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $collective) {
			if ($name === $collective['name']) {
				return $collective['id'];
			}
		}
		return null;
	}

	/**
	 * @param int    $collectiveId
	 * @param string $name
	 *
	 * @return int|null
	 * @throws GuzzleException
	 */
	private function pageIdByName(int $collectiveId, string $name): ?int {
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of pages for collective ' . $collectiveId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $page) {
			if ($name === $page['title']) {
				return $page['id'];
			}
		}
		return null;
	}

	/**
	 * @param int         $collectiveId
	 * @param string      $name
	 * @param string|null $token
	 *
	 * @return int|null
	 * @throws GuzzleException
	 */
	private function trashedPageIdByName(int $collectiveId, string $name, ?string $token = null): ?int {
		if ($token) {
			$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages/trash', null, null, [], false);
		} else {
			$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages/trash');
		}
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of trashed pages for collective ' . $collectiveId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $page) {
			if ($name === $page['title']) {
				return $page['id'];
			}
		}
		return null;
	}

	/**
	 * @param string         $verb
	 * @param string         $url
	 * @param TableNode|null $body
	 * @param array          $headers
	 * @param bool|null      $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendRequest(string $verb,
		string $url,
		?TableNode $body = null,
		?array $jsonBody = null,
		array $headers = [],
		?bool $auth = true): void {
		$fullUrl = $this->baseUrl . $url;
		$this->sendRequestBase($verb, $fullUrl, $body, $jsonBody, $headers, $auth);
	}

	/**
	 * @param string               $verb
	 * @param string               $url
	 * @param string|resource|null $body
	 * @param array                $headers
	 * @param bool|null            $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendRemoteRequest(string $verb,
		string $url,
		$body = null,
		?array $jsonBody = null,
		array $headers = [],
		?bool $auth = true): void {
		$fullUrl = $this->remoteUrl . $url;
		$this->sendRequestBase($verb, $fullUrl, $body, $jsonBody, $headers, $auth);
	}

	/**
	 * @param string         $verb
	 * @param string         $url
	 * @param TableNode|null $body
	 * @param array          $headers
	 * @param bool|null      $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendOcsRequest(string $verb,
		string $url,
		?TableNode $body = null,
		?array $jsonBody = null,
		array $headers = [],
		?bool $auth = true): void {
		$fullUrl = $this->ocsUrl . $url;

		// Add Xdebug trigger variable as GET parameter
		$ocsJsonFormat = 'format=json';
		if (false !== strpos($fullUrl, '?')) {
			$fullUrl .= '&' . $ocsJsonFormat;
		} else {
			$fullUrl .= '?' . $ocsJsonFormat;
		}
		$this->sendRequestBase($verb, $fullUrl, $body, $jsonBody, $headers, $auth);
	}

	/**
	 * @param string                $verb
	 * @param string                $url
	 * @param TableNode|string|null $body
	 * @param array|null            $jsonBody
	 * @param array                 $headers
	 * @param bool|null             $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendRequestBase(string $verb,
		string $url,
		$body = null,
		?array $jsonBody = null,
		array $headers = [],
		?bool $auth = true): void {
		$client = new Client($this->clientOptions);

		if (true === $auth && !isset($this->cookieJars[$this->currentUser])) {
			$this->cookieJars[$this->currentUser] = new CookieJar();
		}

		// Get request token for user (required due to CSRF checks)
		if (true === $auth && !isset($this->requestTokens[$this->currentUser])) {
			$this->getUserRequestToken($this->currentUser);
		}

		$options = ['cookies' => $this->cookieJars[$this->currentUser]];

		$options['headers'] = array_merge($headers, [
			'requesttoken' => $this->requestTokens[$this->currentUser],
		]);

		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			$options['form_params'] = $fd;
		} elseif (is_string($body)) {
			$options['body'] = $body;
		}

		if ($jsonBody) {
			$options['json'] = $jsonBody;
		}

		// Add Xdebug trigger variable as GET parameter
		$xdebugSession = 'XDEBUG_SESSION=PHPSTORM';
		if (false !== strpos($url, '?')) {
			$url .= '&' . $xdebugSession;
		} else {
			$url .= '?' . $xdebugSession;
		}

		// clear the cached json response
		$this->json = null;
		try {
			if ($verb === 'PROPFIND' || $verb === 'MOVE') {
				$this->response = $client->request($verb, $url, $options);
			} else {
				$this->response = $client->{$verb}($url, $options);
			}
		} catch (ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @param string $user
	 *
	 * @throws GuzzleException
	 */
	private function getUserRequestToken(string $user): void {
		$loginUrl = $this->baseUrl . '/login';

		if (!isset($this->requestTokens[$user])) {
			// Request a new session and extract CSRF token
			$client = new Client($this->clientOptions);
			$response = $client->get(
				$loginUrl,
				['cookies' => $this->cookieJars[$user]]
			);
			$requestToken = substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);

			// Login and extract new token
			$client = new Client($this->clientOptions);
			$this->response = $client->post(
				$loginUrl,
				[
					'form_params' => [
						'user' => $user,
						'password' => $user,
						'requesttoken' => $requestToken,
					],
					'cookies' => $this->cookieJars[$user],
				]
			);
			$this->assertStatusCode(200);

			$this->requestTokens[$user] = substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $this->response->getBody()->getContents()), 0, 89);
		}
	}

	/**
	 * @param string $user
	 *
	 * @return string
	 */
	private function getUserLanguage(string $user): string {
		$this->sendOcsRequest('GET', '/cloud/users/' . $user);
		$this->assertStatusCode(200);

		$jsonBody = $this->getJson();
		return $jsonBody['ocs']['data']['language'];
	}

	/**
	 * @param string $user
	 */
	private function setCurrentUser(string $user): void {
		$this->currentUser = $user;
	}

	/**
	 * @param int    $collectiveId
	 * @param string $parentPath
	 *
	 * @return int
	 */
	private function getParentId(int $collectiveId, string $parentPath): int {
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages');
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $page) {
			$path = $page['filePath'] ? $page['filePath'] . '/' . $page['fileName'] : $page['fileName'];
			if ($parentPath === $path) {
				return $page['id'];
			}
		}
		throw new RuntimeException('Could not get parent page id for ' . $parentPath);
	}

	/**
	 * @param int $collectiveId
	 * @param int $pageId
	 *
	 * @return string|null
	 */
	private function getShareToken(int $collectiveId, int $pageId = 0): ?string {
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/shares');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $share) {
			if ($collectiveId === $share['collectiveId'] && $pageId === $share['pageId']) {
				return $share['token'];
			}
		}
		return null;
	}

	/**
	 * @param mixed    $statusCode
	 * @param string   $message
	 */
	private function assertStatusCode($statusCode, string $message = ''): void {
		if (is_int($statusCode)) {
			$message = $message ?: 'Status code ' . $this->response->getStatusCode() . ' is not expected ' . $statusCode . '.';
			Assert::assertEquals($statusCode, $this->response->getStatusCode(), $message);
		} elseif (is_array($statusCode)) {
			$message = $message ?: 'Status code ' . $this->response->getStatusCode() . ' is neither of ' . implode(', ', $statusCode) . '.';
			Assert::assertContains($this->response->getStatusCode(), $statusCode, $message);
		}
	}

	/**
	 * @param string    $name
	 * @param bool|null $revert
	 */
	private function assertCollectiveByName(string $name, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		$collectiveNames = [];
		foreach ($jsonBody['data'] as $collective) {
			$collectiveNames[] = $collective['name'];
		}
		if (false === $revert) {
			Assert::assertContains($name, $collectiveNames);
		} else {
			Assert::assertNotContains($name, $collectiveNames);
		}
	}


	/**
	 * @param string    $name
	 * @param string    $key
	 * @param string    $value
	 * @param bool|null $revert
	 */
	private function assertCollectiveKeyValue(string $name, string $key, string $value, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $c) {
			if ($c['name'] === $name) {
				$collective = $c;
			}
		}

		if (!isset($collective)) {
			throw new RuntimeException('Unable to find collective ' . $name);
		}

		if (false === $revert) {
			Assert::assertEquals($value, $collective[$key]);
		} else {
			Assert::assertNotEquals($value, $collective[$key]);
		}
	}

	/**
	 * @param string $name
	 * @param int    $level
	 */
	private function assertCollectiveLevel(string $name, int $level): void {
		$data = $this->getJson()['data'];
		// Dirty hack. We don't know whether $data contains the collective (e.g. after collective#create)
		// or an array of collectives (e.g. after collectives#index or publicCollectives#get)
		if (array_key_exists(0, $data) && !array_key_exists('name', $data)) {
			$collective = $data[0];
		} else {
			$collective = $data;
		}
		Assert::assertEquals($name, $collective['name']);
		Assert::assertEquals($level, $collective['level']);
	}

	/**
	 * @param string    $path
	 * @param bool|null $revert
	 */
	private function assertPageByPath(string $path, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		$pagePaths = [];
		foreach ($jsonBody['data'] as $page) {
			$pagePaths[] = $page['filePath'] ? $page['filePath'] . '/' . $page['fileName'] : $page['fileName'];
		}
		if (false === $revert) {
			Assert::assertContains($path, $pagePaths);
		} else {
			Assert::assertNotContains($path, $pagePaths);
		}
	}

	/**
	 * @param int       $id
	 * @param string    $key
	 * @param mixed     $value
	 * @param bool|null $revert
	 */
	private function assertPageKeyValue(int $id, string $key, $value, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		$page = $jsonBody['data'];

		if (!isset($page)) {
			throw new RuntimeException('Unable to find page with ID ' . $id);
		}

		if (false === $revert) {
			Assert::assertEquals($value, $page[$key]);
		} else {
			Assert::assertNotEquals($value, $page[$key]);
		}
	}

	/**
	 * @param string    $title
	 * @param string    $user
	 */
	private function assertPageLastEditedByUser(string $title, string $user): void {
		$jsonBody = $this->getJson();
		$pageTitles = [];
		foreach ($jsonBody['data'] as $page) {
			if ($page['lastUserId'] === $user) {
				$pageTitles[] = $page['title'];
			}
		}
		Assert::assertContains($title, $pageTitles);
	}

	/**
	 * @param string $name
	 * @param string $mimetype
	 */
	private function assertAttachment(string $name, string $mimetype): void {
		$jsonBody = $this->getJson();
		$attachment = [
			'name' => $name,
			'mimetype' => $mimetype,
		];

		$pageAttachments = array_map(function ($attachment) {
			return [
				'name' => $attachment['name'],
				'mimetype' => $attachment['mimetype'],
			];
		}, $jsonBody['data']);

		Assert::assertContains($attachment, $pageAttachments);
	}
}
