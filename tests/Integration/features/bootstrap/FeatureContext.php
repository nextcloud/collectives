<?php

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context {
	/** @var Response */
	private $response;

	/** @var array */
	private $json;

	/** @var string */
	private $currentUser;

	/** @var string */
	private $baseUrl;

	/** @var string */
	private $remoteUrl;

	/** @var string */
	private $ocsUrl;

	/** @var CookieJar[] */
	private $cookieJars;

	/** @var string[] */
	private $requestTokens;

	/** @var array */
	private $clientOptions;

	/** @var array */
	private $store;

	private const CIRCLE_MEMBER_LEVEL = [
		1 => 'Member',
		4 => 'Moderator',
		8 => 'Admin',
		9 => 'Owner'
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
	 * @When user :user creates page :page with parentPath :parentPath in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $parentPath
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesPage(string $user, string $page, string $parentPath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page], ['parentId', $parentId]]);
		$this->sendRequest('POST', '/apps/collectives/_api/' . $collectiveId . '/_pages/parent/' . $parentId, $formData);
		$this->assertStatusCode(200);
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
	 *
	 * @param string $user
	 * @param string $pagePath
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userSeesPagePath(string $user, string $pagePath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages');
		$this->assertStatusCode(200);
		$this->assertPageByPath($pagePath);
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
	 * @When user :user deletes page :page with parentPath :parentPath in :collective
	 * @When user :user :fails to delete page :page with parentPath :parentPath in :collective
	 *
	 * @param string      $user
	 * @param string      $page
	 * @param string      $collective
	 * @param string      $parentPath
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesPage(string $user, string $page, string $collective, string $parentPath, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/_pages/parent/' . $parentId . '/page/' . $pageId);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user touches page :page with parentPath :parentPath in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $parentPath
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 * @throws JsonException
	 */
	public function userTouchesPage(string $user, string $page, string $parentPath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$this->sendRequest('GET', '/apps/collectives/_api/' . $collectiveId . '/_pages/parent/' . $parentId . '/page/' . $pageId . '/touch');
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user renames page :page to :newtitle with parentPath :parentPath in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $newtitle
	 * @param string $parentPath
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userRenamesPage(string $user, string $page, string $newtitle, string $parentPath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([['title', $newtitle]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/_pages/parent/' . $parentId . '/page/' . $pageId, $formData);
		$this->assertStatusCode(200);
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
		$this->assertStatusCode(200);

		if ($level) {
			$jsonBody = $this->getJson();
			$memberId = $jsonBody['ocs']['data']['id'];
			$data = new TableNode([
				['level', $level],
			]);

			$this->sendOcsRequest('PUT', '/apps/circles/circles/' . $circleId . '/members/' . $memberId . '/level', $data);
			$this->assertStatusCode(200);
		}
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
	 *
	 * @param string $user
	 * @param string $name
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesCircle(string $user, string $name): void {
		$this->setCurrentUser($user);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
		$this->sendOcsRequest('DELETE', '/apps/circles/circles/' . $circleId);
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
	 * @When user :user sets editing permissions for collective :collective
	 *
	 * @param string $user
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPublicShareEditPermissions(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getCollectiveShareToken($collectiveId);
		$formData = new TableNode([['editable', true]]);
		$this->sendRequest('PUT', '/apps/collectives/_api/' . $collectiveId . '/share/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user stores token for public share :collective
	 *
	 * @param string $user
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userStoresPublicShareToken(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->store['shareToken'] = $this->getCollectiveShareToken($collectiveId);
	}

	/**
	 * @When user :user deletes public share for :collective
	 *
	 * @param string $user
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesPublicShare(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getCollectiveShareToken($collectiveId);
		$this->sendRequest('DELETE', '/apps/collectives/_api/' . $collectiveId . '/share/' . $token);
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
		$token = $this->getCollectiveShareToken($collectiveId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token, null, [], false);
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective);
		$this->assertCollectiveLevel($collective, 1);
	}

	/**
	 * @When anonymous fails to see public collective :collective with stored token
	 *
	 * @param string $collective
	 *
	 * @throws GuzzleException
	 */
	public function anonymousFailsToSeePublicCollective(string $collective): void {
		Assert::assertArrayHasKey('shareToken', $this->store);
		Assert::assertNotEmpty($this->store['shareToken']);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $this->store['shareToken'], null, [], false);
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
		$token = $this->getCollectiveShareToken($collectiveId);
		$this->sendRequest('GET', '/apps/collectives/_api/p/' . $token . '/_pages', null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path);
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
		$token = $this->getCollectiveShareToken($collectiveId);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page], ['parentId', $parentId]]);
		$this->sendRequest('POST', '/apps/collectives/_api/p/' . $token . '/_pages/parent/' . $parentId, $formData, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous deletes page :page with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to delete page :page with parentPath :parentPath in public collective :collective with owner :owner
	 *
	 * @param string      $page
	 * @param string      $collective
	 * @param string      $parentPath
	 * @param string      $owner
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDeletesPublicCollectivePage(string $page, string $collective, string $parentPath, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getCollectiveShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$this->sendRequest('DELETE', '/apps/collectives/_api/p/' . $token . '/_pages/parent/' . $parentId . '/page/' . $pageId, null, [], false);
		if ("fails" === $fail) {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
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
		$userCollectivesPath = 'Collectives';

		// Dirty hack to not break it on local dev setup
		$lang = $this->getUserLanguage($user);
		if ($lang === 'de') {
			$userCollectivesPath = 'Kollektive';
		}

		$this->sendRemoteRequest('PROPFIND', '/dav/files/' . $user . '/' . $userCollectivesPath . '/' . urlencode($collective) . '/', $body, $headers);
		$this->assertStatusCode(207);

		// simplexml_load_string() would be better than preg_replace
		$folderPermissions = preg_replace('/.*<oc:permissions>(.*)<\/oc:permissions>.*/sm', '\1', $this->response->getBody()->getContents());

		Assert::assertEquals($permissions, $folderPermissions);
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
								 array $headers = [],
								 ?bool $auth = true): void {
		$fullUrl = $this->baseUrl . $url;
		$this->sendRequestBase($verb, $fullUrl, $body, $headers, $auth);
	}

	/**
	 * @param string      $verb
	 * @param string      $url
	 * @param string|null $body
	 * @param array       $headers
	 * @param bool|null   $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendRemoteRequest(string $verb,
								 string $url,
								 ?string $body = null,
								 array $headers = [],
								 ?bool $auth = true): void {
		$fullUrl = $this->remoteUrl . $url;
		$this->sendRequestBase($verb, $fullUrl, $body, $headers, $auth);
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
		$this->sendRequestBase($verb, $fullUrl, $body, $headers, $auth);
	}

	/**
	 * @param string                $verb
	 * @param string                $url
	 * @param TableNode|string|null $body
	 * @param array                 $headers
	 * @param bool|null             $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendRequestBase(string $verb,
								 string $url,
								 $body = null,
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
			if ($verb === 'PROPFIND') {
				$this->response = $client->request('PROPFIND', $url, $options);
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
	 * @param int    $collectiveId
	 *
	 * @return string|null
	 */
	private function getCollectiveShareToken(int $collectiveId): ?string {
		$this->sendRequest('GET', '/apps/collectives/_api');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['data'] as $collective) {
			if ($collectiveId === $collective['id']) {
				return $collective['shareToken'];
			}
		}
		return null;
	}

	/**
	 * @param int      $statusCode
	 * @param string   $message
	 */
	private function assertStatusCode(int $statusCode, string $message = ''): void {
		Assert::assertEquals($statusCode, $this->response->getStatusCode(), $message);
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
			throw new RuntimeException('Unable to find collective ' . $collective);
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
}
