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

	/** @var string */
	private $currentUser;

	/** @var string */
	private $baseUrl;

	/** @var string */
	private $ocsUrl;

	/** @var CookieJar[] */
	private $cookieJars;

	/** @var string[] */
	private $requestTokens;

	/** @var array */
	private $clientOptions;

	/**
	 * Initializes context.
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 *
	 * @param string $baseUrl
	 * @param string $ocsUrl
	 */
	public function __construct(string $baseUrl, string $ocsUrl) {
		$this->baseUrl = $baseUrl;
		$this->ocsUrl = $ocsUrl;
		$this->clientOptions = ['verify' => false];
	}

	/**
	 * @When user :user creates collective :collective
	 * @When user :user :fails to create collective :collective
	 *
	 * @param string $user
	 * @param string $collective
	 * @param string|null $fail
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesCollective(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$formData = new TableNode([['name', $collective]]);
		$this->sendRequest('POST', '/apps/collectives/_collectives', $formData);
		if ("fails" === $fail) {
			$this->assertStatusCode($this->response, 422);
		} else {
			$this->assertStatusCode($this->response, 200);
		}
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
		$this->sendRequest('POST', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/parent/' . $parentId, $formData);
		$this->assertStatusCode($this->response, 200);
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
			$this->sendRequest('GET', '/apps/collectives/_collectives/trash');
		} else {
			$this->sendRequest('GET', '/apps/collectives/_collectives');
		}
		$this->assertStatusCode($this->response, 200);
		$this->assertCollectiveByName($this->response, $collective);
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
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		$this->assertStatusCode($this->response, 200);
		$this->assertPageByPath($this->response, $pagePath);
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
			$this->sendRequest('GET', '/apps/collectives/_collectives/trash');
		} else {
			$this->sendRequest('GET', '/apps/collectives/_collectives');
		}
		$this->assertStatusCode($this->response, 200);
		$this->assertCollectiveByName($this->response, $collective, true);
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
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		$this->assertStatusCode($this->response, 200);
		$this->assertPageByPath($this->response, $pagePath, true);
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
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		$this->assertStatusCode($this->response, 200);
		$this->assertPageLastEditedByUser($this->response, $page, $user);
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
		$this->sendRequest('DELETE', '/apps/collectives/_collectives/' . $collectiveId);
		if ("fails" === $fail) {
			$this->assertStatusCode($this->response, $member ? 404 : 403);
		} else {
			$this->assertStatusCode($this->response, 200);
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

		$this->sendRequest('DELETE', '/apps/collectives/_collectives/trash/' . $collectiveId);
		if ("fails" === $fail) {
			$this->assertStatusCode($this->response, 404);
		} else {
			$this->assertStatusCode($this->response, 200);
		}
	}

	/**
	 * @When user :user deletes collective+circle :collective
	 * @When user :user :fails to delete collective+circle :collective
	 * @When user :user :fails to delete collective+circle :collective with admin :admin
	 *
	 * @param string      $user
	 * @param string      $collective
	 * @param string|null $fail
	 * @param string|null $admin
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesCollectiveAndCircle(string $user, string $collective, ?string $fail = null, ?string $admin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/collectives/_collectives/trash/' . $collectiveId . '?circle=1');
		if ("fails" === $fail) {
			$this->assertStatusCode($this->response, 404);
		} else {
			$this->assertStatusCode($this->response, 200);
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
		$this->sendRequest('PATCH', '/apps/collectives/_collectives/trash/' . $collectiveId);
		if ("fails" === $fail) {
			$this->assertStatusCode($this->response, 404);
		} else {
			$this->assertStatusCode($this->response, 200);
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
		$this->sendRequest('DELETE', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/parent/' . $parentId . '/page/' . $pageId);
		if ("fails" === $fail) {
			$this->assertStatusCode($this->response, 403);
		} else {
			$this->assertStatusCode($this->response, 200);
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
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/parent/' . $parentId . '/page/' . $pageId . '/touch');
		$this->assertStatusCode($this->response, 200);
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
		$this->sendRequest('PUT', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/parent/' . $parentId . '/page/' . $pageId, $formData);
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @When user :user joins circle :name with admin :admin
	 *
	 * @param string $user
	 * @param string $name
	 * @param string $admin
	 *
	 * @throws GuzzleException
	 */
	public function userJoinsCircle(string $user, string $name, string $admin): void {
		$this->setCurrentUser($admin);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);

		$data = new TableNode([
			['ident', $user],
			['type', 1],
			['instance', '']
		]);
		$this->sendRequest('PUT', '/apps/circles/v1/circles/' . $circleId . '/member', $data);
		$this->assertStatusCode($this->response, 201);
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
		$this->sendRequest('DELETE', '/apps/circles/v1/circles/' . $circleId);
		$this->assertStatusCode($this->response, 201);
	}

	/**
	 * @param string $name
	 *
	 * @return string|null
	 * @throws GuzzleException
	 */
	private function circleIdByName(string $name): ?string {
		$this->sendRequest('GET', '/apps/circles/v1/circles?type=14');
		if (201 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of circles');
		}
		$jsonBody = json_decode($this->response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
		foreach ($jsonBody['data'] as $circle) {
			if ($name === $circle['name']) {
				return $circle['unique_id'];
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
			$this->sendRequest('GET', '/apps/collectives/_collectives/trash');
		} else {
			$this->sendRequest('GET', '/apps/collectives/_collectives');
		}
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$jsonBody = json_decode($this->response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of pages for collective ' . $collectiveId);
		}
		$jsonBody = json_decode($this->response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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
	 * @param string         $verb
	 * @param string         $url
	 * @param TableNode|null $body
	 * @param array          $headers
	 * @param bool|null      $auth
	 *
	 * @throws GuzzleException
	 */
	private function sendRequestBase(string $verb,
								 string $url,
								 ?TableNode $body = null,
								 array $headers = [],
								 ?bool $auth = true): void {
		$client = new Client($this->clientOptions);

		if (!isset($this->cookieJars[$this->currentUser])) {
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

		if (null !== $body) {
			$fd = $body->getRowsHash();
			$options['form_params'] = $fd;
		}

		// Add Xdebug trigger variable as GET parameter
		$xdebugSession = 'XDEBUG_SESSION=PHPSTORM';
		if (false !== strpos($url, '?')) {
			$url .= '&' . $xdebugSession;
		} else {
			$url .= '?' . $xdebugSession;
		}

		try {
			$this->response = $client->{$verb}($url, $options);
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
			$response = $client->post(
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
			$this->assertStatusCode($response, 200);

			$this->requestTokens[$user] = substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);
		}
	}

	/**
	 * @param string $user
	 */
	private function setCurrentUser(string $user): void {
		$this->currentUser = $user;
	}

	/**
	 * @param Response $response
	 * @param string   $name
	 *
	 * @return string|null
	 */
	private function getCircleIdByCollectiveName(Response $response, string $name): ?string {
		$jsonBody = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
		foreach ($jsonBody['data'] as $collective) {
			if ($name === $collective['name']) {
				return $collective['circleUniqueId'];
			}
		}
		return null;
	}

	/**
	 * @param int    $collectiveId
	 * @param string $parentPath
	 *
	 * @return int
	 */
	private function getParentId(int $collectiveId, string $parentPath): int {
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		$jsonBody = json_decode($this->response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
		foreach ($jsonBody['data'] as $page) {
			$path = $page['filePath'] ? $page['filePath'] . '/' . $page['fileName'] : $page['fileName'];
			if ($parentPath === $path) {
				return $page['id'];
			}
		}
		throw new RuntimeException('Could not get parent page id for ' . $parentPath);
	}

	/**
	 * @param Response $response
	 * @param int      $statusCode
	 * @param string   $message
	 */
	private function assertStatusCode(Response $response, int $statusCode, string $message = ''): void {
		Assert::assertEquals($statusCode, $response->getStatusCode(), $message);
	}

	/**
	 * @param Response  $response
	 * @param string    $name
	 * @param bool|null $revert
	 */
	private function assertCollectiveByName(Response $response, string $name, ?bool $revert = false): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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
	 * @param Response  $response
	 * @param string    $path
	 * @param bool|null $revert
	 */
	private function assertPageByPath(Response $response, string $path, ?bool $revert = false): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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
	 * @param Response  $response
	 * @param string    $title
	 * @param string    $user
	 */
	private function assertPageLastEditedByUser(Response $response, string $title, string $user): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
		$pageTitles = [];
		foreach ($jsonBody['data'] as $page) {
			if ($page['lastUserId'] === $user) {
				$pageTitles[] = $page['title'];
			}
		}
		Assert::assertContains($title, $pageTitles);
	}
}
