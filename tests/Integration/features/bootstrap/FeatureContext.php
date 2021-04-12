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

	/** @var CookieJar[] */
	private $cookieJars;

	/** @var string[] */
	private $requestTokens;

	/** @var array */
	private $clientOptions;

	/** @var string */
	private $cruftCircleUniqueId;

	/**
	 * Initializes context.
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 *
	 * @param $baseUrl
	 */
	public function __construct($baseUrl) {
		$this->baseUrl = $baseUrl;
		$this->clientOptions = ['verify' => false];
	}

	/**
	 * @When user :user creates collective :collective
	 * @When user :user :fails to create collective :collective
	 *
	 * @param string $user
	 * @param string $collective
	 * @param string|null $fail
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
	 * @When user :user creates page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 */
	public function userCreatesPage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$formData = new TableNode([['title', $page]]);
		$this->sendRequest('POST', '/apps/collectives/_collectives/' . $collectiveId . '/_pages', $formData);
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @Then user :user sees collective :collective
	 * @Then user :user sees collective :collective in :trash
	 *
	 * @param string $user
	 * @param string $collective
	 * @param string $trash
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
	 * @Then user :user sees page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 */
	public function userSeesPage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		$this->assertStatusCode($this->response, 200);
		$this->assertPageByTitle($this->response, $page);
	}

	/**
	 * @Then user :user doesn't see collective :collective
	 * @Then user :user doesn't see collective :collective in :trash
	 *
	 * @param string $user
	 * @param string $collective
	 * @param string $trash
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
	 * @Then user :user doesn't see page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 */
	public function userDoesntSeePage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		$this->assertStatusCode($this->response, 200);
		$this->assertPageByTitle($this->response, $page, true);
	}

	/**
	 * @Then user :user last edited page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
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
	 */
	public function userDeletesCollective(string $user, string $collective, ?string $fail = null, ?string $admin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);

		// Store circleUniqueId for later usage
		if ("fails" !== $fail) {
			$this->sendRequest('GET', '/apps/collectives/_collectives/trash');
			$this->assertStatusCode($this->response, 200);
			if (null !== $cruftCircleUniqueId = $this->getCircleIdByCollectiveName($this->response, $collective)) {
				$this->cruftCircleUniqueId = $cruftCircleUniqueId;
			}
		}

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
	 */
	public function userDeletesCollectiveAndCircle(string $user, string $collective, ?string $fail = null, ?string $admin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if (null === $collectiveId) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/collectives/_collectives/trash/' . $collectiveId . '/all');
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
	 * @When user :user deletes page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 */
	public function userDeletesPage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('DELETE', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/' . $pageId);
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @When user :user touches page :page in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $collective
	 */
	public function userTouchesPage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/' . $pageId . '/touch');
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @When user :user renames page :page to :newpage in :collective
	 *
	 * @param string $user
	 * @param string $page
	 * @param string $newpage
	 * @param string $collective
	 */
	public function userRenamesPage(string $user, string $page, string $newpage, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$formData = new TableNode([['title', $newpage]]);
		$this->sendRequest('PUT', '/apps/collectives/_collectives/' . $collectiveId . '/_pages/' . $pageId, $formData);
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @When user :user is member of circle :circle with admin :admin
	 *
	 * @param string $user
	 * @param string $circle
	 * @param string $admin
	 */
	public function userIsMemberOfCircle(string $user, string $circle, string $admin): void {
		$this->setCurrentUser($admin);
		$this->sendRequest('GET', '/apps/collectives/_collectives');
		$this->assertStatusCode($this->response, 200);
		$circleUniqueId = $this->getCircleIdByCollectiveName($this->response, $circle);

		$data = new TableNode([
			['ident', $user],
			['type', 1],
			['instance', '']
		]);
		$this->sendRequest('PUT', '/apps/circles/v1/circles/' . $circleUniqueId . '/member', $data);
		$this->assertStatusCode($this->response, 201);
	}

	/**
	 * @When user :user deletes cruft circle
	 *
	 * @param string $user
	 */
	public function userDeletesCruftCircle(string $user): void {
		Assert::assertNotEmpty($this->cruftCircleUniqueId);
		$this->setCurrentUser($user);
		$this->sendRequest('DELETE', '/apps/circles/v1/circles/' . $this->cruftCircleUniqueId);
		$this->assertStatusCode($this->response, 201);
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
		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		foreach ($jsonBody as $collective) {
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
	 */
	private function pageIdByName(int $collectiveId, string $name): ?int {
		$this->sendRequest('GET', '/apps/collectives/_collectives/' . $collectiveId . '/_pages');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of pages for collective ' . $collectiveId);
		}
		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		foreach ($jsonBody as $page) {
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
		$fullUrl = $fullUrl . '?XDEBUG_SESSION=PHPSTORM';

		try {
			$this->response = $client->{$verb}($fullUrl, $options);
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
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		foreach ($jsonBody as $collective) {
			if ($name === $collective['name']) {
				return $collective['circleUniqueId'];
			}
		}
		return null;
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
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		$collectiveNames = [];
		foreach ($jsonBody as $collective) {
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
	 * @param string    $title
	 * @param bool|null $revert
	 */
	private function assertPageByTitle(Response $response, string $title, ?bool $revert = false): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		$pageTitles = [];
		foreach ($jsonBody as $page) {
			$pageTitles[] = $page['title'];
		}
		if (false === $revert) {
			Assert::assertContains($title, $pageTitles);
		} else {
			Assert::assertNotContains($title, $pageTitles);
		}
	}

	/**
	 * @param Response  $response
	 * @param string    $title
	 * @param string    $user
	 */
	private function assertPageLastEditedByUser(Response $response, string $title, string $user): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		$pageTitles = [];
		foreach ($jsonBody as $page) {
			if ($page['lastUserId'] === $user) {
				$pageTitles[] = $page['title'];
			}
		}
		Assert::assertContains($title, $pageTitles);
	}
}
