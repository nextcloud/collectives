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
	private $createdWikiIds;

	/** @var array */
	private $clientOptions;

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
	 * @When user :user creates wiki :name
	 *
	 * @param string $user
	 * @param string $name
	 */
    public function userCreatesWiki(string $user, string $name): void {
    	$this->setCurrentUser($user);
		$formData = new TableNode([['name', $name]]);
    	$this->sendRequest('POST', '/apps/wiki/_wikis', $formData);
    	$this->assertStatusCode($this->response, 200);
		$this->addCreatedWikiId($this->response);
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
		$this->sendRequest('GET', '/apps/wiki/_wikis');
		$this->assertStatusCode($this->response, 200);
		$circleUniqueId = $this->getCircleIdByWikiName($this->response, $circle);

		$data = new TableNode([
			['ident', $user],
			['type', 8],
			['instance', '']
		]);
		$this->sendRequest('PUT', '/apps/circles/v1/circles/' . $circleUniqueId . '/member', $data);
		$this->assertStatusCode($this->response, 201);
    }

	/**
	 * @Then user :user sees wiki :name
	 *
	 * @param string $user
	 * @param string $name
	 */
    public function userSeesWiki(string $user, string $name): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/wiki/_wikis');
		$this->assertStatusCode($this->response, 200);
		$this->assertWikiByName($this->response, $name);
    }

	/**
	 * @Then user :user doesn't see wiki :name
	 *
	 * @param string $user
	 * @param string $name
	 */
    public function userDoesntSeeWiki(string $user, string $name): void {
		$this->setCurrentUser($user);
		$this->sendRequest('GET', '/apps/wiki/_wikis');
		$this->assertStatusCode($this->response, 200);
		$this->assertWikiByName($this->response, $name, true);
    }

	/**
	 * @When user :user deletes wiki :name
	 * @When user :user :fails to delete wiki :name
	 *
	 * @param string $user
	 * @param string $name
	 * @param string $fail
	 */
    public function userDeletesWiki(string $user, string $name, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$wikiId = $this->wikiIdByName($name);
		if ("fails" === $fail) {
			if (null !== $wikiId) {
				throw new RuntimeException('Got a wikiId while not expecting one');
			}
			return;
		}
		$this->sendRequest('DELETE', '/apps/wiki/_wikis/' . $wikiId);
		$this->assertStatusCode($this->response, 200);
	}

	/**
	 * @param string $name
	 *
	 * @return int|null
	 */
	private function wikiIdByName(string $name): ?int {
		$this->sendRequest('GET', '/apps/wiki/_wikis');
		if (200 !== $this->response->getStatusCode()) {
			throw new RuntimeException('Unable to get list of wikis');
		}
		$jsonBody = json_decode($this->response->getBody()->getContents(), true);
		foreach ($jsonBody as $wiki) {
			if ($name === $wiki['name']) {
				return $wiki['id'];
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
	 */
	private function addCreatedWikiId(Response $response): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		$this->createdWikiIds[] = [
			'user' => $this->currentUser,
			'id' => $jsonBody['id']
		];
	}

	/**
	 * @param Response $response
	 * @param string   $name
	 *
	 * @return string|null
	 */
	private function getCircleIdByWikiName(Response $response, string $name): ?string {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		foreach ($jsonBody as $wiki) {
			if ($name === $wiki['name']) {
				return $wiki['circleUniqueId'];
			}
		}
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
	private function assertWikiByName(Response $response, string $name, ?bool $revert = false): void {
		$jsonBody = json_decode($response->getBody()->getContents(), true);
		$wikiNames = [];
		foreach ($jsonBody as $wiki) {
			$wikiNames[] = $wiki['name'];
		}
		if (false === $revert) {
			Assert::assertContains($name, $wikiNames);
		} else {
			Assert::assertNotContains($name, $wikiNames);
		}
	}
}
