<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
	private array $clientOptions;
	private ?ResponseInterface $response = null;
	private ?array $json = null;
	private ?string $currentUser = null;
	private ?string $nextcloudVersion = null;
	private array $cookieJars = [];
	private array $requestTokens = [];
	private array $store = [];
	private bool $setXdebugSession = false;

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

	private const API_URL = '/apps/collectives/api/v1.0/';

	/**
	 * Initializes context.
	 * Every scenario gets its own context instance.
	 * You can also pass arbitrary arguments to the
	 * context constructor through behat.yml.
	 */
	public function __construct(
		private string $baseUrl,
		private string $ocsUrl,
		public string $publicUrl,
		private string $remoteUrl,
	) {
		$this->clientOptions = ['verify' => false];
	}

	/**
	 * @When user :user creates collective :collective
	 * @When user :user :fails to create collective :collective
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesCollective(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$formData = new TableNode([['name', $collective]]);
		$this->sendOcsCollectivesRequest('POST', 'collectives', $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(400);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user sets :type level in collective :collective to :level
	 *
	 * @throws GuzzleException
	 */
	public function userUpdatesCollectivePermissionLevel(string $user, string $type, string $collective, string $level): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);

		$intLevel = array_search($level, self::CIRCLE_MEMBER_LEVEL, true);
		if (!$intLevel) {
			throw new RuntimeException('Could not verify team member level ' . $level);
		}

		$formData = new TableNode([['level', $intLevel]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/' . $type . 'Level', $formData);
		$this->assertStatusCode(200);

		$this->sendOcsCollectivesRequest('GET', 'collectives');
		$this->assertCollectiveKeyValue($collective, $type . 'PermissionLevel', (string)$intLevel);
	}

	/**
	 * @When user :user sets pageMode for collective :collective to :mode
	 * @When user :user :fails to set pageMode for collective :collective to :mode
	 *
	 * @throws GuzzleException
	 */
	public function userUpdatesCollectivePageMode(string $user, string $collective, string $mode, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);

		$intMode = array_search($mode, self::PAGE_MODE, true);
		if (!$intMode) {
			throw new RuntimeException('Could not verify page mode ' . $mode);
		}

		$formData = new TableNode([['mode', $intMode]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pageMode', $formData);

		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);

			$this->sendOcsCollectivesRequest('GET', 'collectives');
			$this->assertCollectiveKeyValue($collective, 'pageMode', (string)$intMode);
		}
	}

	/**
	 * @When user :user creates page :page with parentPath :parentPath in :collective
	 * @When user :user :fails to create page :page with parentPath :parentPath in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesPage(string $user, string $page, string $parentPath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page]]);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/pages/' . $parentId, $formData);

		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user creates page :page with parentPath :parentPath from template :template in :collective
	 * @When user :user :fails to create page :page with parentPath :parentPath from template :template in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesFromTemplate(string $user, string $page, string $parentPath, string $template, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$templateId = $this->templateIdByName($collectiveId, $template);

		$formData = new TableNode([['title', $page], ['templateId', $templateId]]);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/pages/' . $parentId, $formData);

		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user creates template :template in :collective
	 * @When user :user :fails to create template :template in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesTemplate(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);

		$formData = new TableNode([['title', $page], ['parentId', 0]]);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/pages/templates/0', $formData);

		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}


	/**
	 * @Then user :user sees collective :collective
	 * @Then user :user sees collective :collective in :trash
	 *
	 * @throws GuzzleException
	 */
	public function userSeesCollective(string $user, string $collective, ?string $trash = null): void {
		$this->setCurrentUser($user);
		if ($trash) {
			$this->sendOcsCollectivesRequest('GET', 'collectives/trash');
		} else {
			$this->sendOcsCollectivesRequest('GET', 'collectives');
		}
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective);
	}

	/**
	 * @Then user :user sees pagePath :pagePath in :collective
	 * @Then user :user :fails to see pagePath :pagePath in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSeesPagePath(string $user, string $pagePath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages');
		$this->assertStatusCode(200);
		if ($fail === 'fails') {
			$this->assertPageByPath($pagePath, true);
		} else {
			$this->assertPageByPath($pagePath);
		}
	}

	/**
	 * @Then user :user sees templateName :templateName in :collective
	 * @Then user :user :fails to see templateName :templateName in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSeesTemplatePath(string $user, string $templateName, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$hasTemplateId = $this->expectTemplateId($collectiveId, $templateName);
		if ($fail === 'fails') {
			Assert::assertFalse($hasTemplateId);
		} else {
			Assert::assertTrue($hasTemplateId);
		}
	}

	/**
	 * @Then user :user doesn't see collective :collective
	 * @Then user :user doesn't see collective :collective in :trash
	 *
	 * @throws GuzzleException
	 */
	public function userDoesntSeeCollective(string $user, string $collective, ?string $trash = null): void {
		$this->setCurrentUser($user);
		if ($trash) {
			$this->sendOcsCollectivesRequest('GET', 'collectives/trash');
		} else {
			$this->sendOcsCollectivesRequest('GET', 'collectives');
		}
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective, true);
	}

	/**
	 * @Then user :user doesn't see pagePath :pagePath in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userDoesntSeePagePath(string $user, string $pagePath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages');
		$this->assertStatusCode(200);
		$this->assertPageByPath($pagePath, true);
	}

	/**
	 * @Then user :user sees attachment :name with mimetype :mimetype for :page in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSeesAttachments(string $user, string $name, string $mimetype, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/attachments');
		$this->assertStatusCode(200);
		$this->assertAttachment($name, $mimetype);
	}

	/**
	 * @Then user :user last edited page :page in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userLastEditedPage(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages');
		$this->assertStatusCode(200);
		$this->assertPageLastEditedByUser($page, $user);
	}

	/**
	 * @When user :user trashes collective :collective
	 * @When user :user :fails to trash collective :collective
	 * @When user :user :fails to trash foreign collective :collective with member :member
	 *
	 * @throws GuzzleException
	 */
	public function userTrashesCollective(string $user, string $collective, ?string $fail = null, ?string $member = null): void {
		$this->setCurrentUser($member ?: $user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ($collectiveId === null) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId);
		if ($fail === 'fails') {
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
	 * @throws GuzzleException
	 */
	public function userDeletesCollective(string $user, string $collective, ?string $fail = null, ?string $admin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if ($collectiveId === null) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);

		$this->sendOcsCollectivesRequest('DELETE', 'collectives/trash/' . $collectiveId);
		if ($fail === 'fails') {
			$this->assertStatusCode(404);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user deletes collective+team :collective
	 * @When user :user :fails to delete collective+team :collective
	 * @When user :user :fails to delete collective+team :collective with admin :admin
	 * @When user :user :fails to delete :selfadmin collective+team :collective
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesCollectiveAndTeam(string $user, string $collective, ?string $fail = null, ?string $admin = null, ?string $selfadmin = null): void {
		$this->setCurrentUser($admin ?: $user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if ($collectiveId === null) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->setCurrentUser($user);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/trash/' . $collectiveId . '?circle=1');
		if ($fail === 'fails') {
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
	 * @throws GuzzleException
	 */
	public function userRestoresCollective(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective, true);
		if ($collectiveId === null) {
			throw new RuntimeException('Could not get collectiveId for ' . $collective);
		}
		$this->sendOcsCollectivesRequest('PATCH', 'collectives/trash/' . $collectiveId);
		if ($fail === 'fails') {
			$this->assertStatusCode(404);
		} else {
			$this->assertStatusCode(200);
			$this->assertCollectiveLevel($collective, 9);
		}
	}

	/**
	 * @When user :user collective :collective property :property is :value
	 *
	 * @throws GuzzleException
	 */
	public function userCollectiveProperty(string $user, string $collective, string $property, string $value): void {
		$this->setCurrentUser($user);
		$this->sendOcsCollectivesRequest('GET', 'collectives');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$this->assertCollectiveKeyValue($collective, $property, $value);
	}

	/**
	 * @When user :user sets userSetting :userSetting for collective :collective to :value
	 *
	 * @throws GuzzleException
	 */
	public function userSetsCollectiveUserSetting(string $user, string $userSetting, string $collective, string $value): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$formData = new TableNode([[$userSetting, $value]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/userSettings/' . $userSetting, $formData);
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to set userSetting for collective');
		}

		$this->userCollectiveProperty($user, $collective, 'user' . ucfirst($userSetting), $value);
	}

	/**
	 * @When user :user trashes page :page in :collective
	 * @When user :user :fails to trash page :page in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userTrashesPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/pages/' . $pageId);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user restores page :page from trash in :collective
	 * @When user :user :fails to restore page :page from trash in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userRestoresPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ($fail === 'fails') {
			$this->sendOcsCollectivesRequest('PATCH', 'collectives/' . $collectiveId . '/pages/trash/' . 1);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page);
			$this->sendOcsCollectivesRequest('PATCH', 'collectives/' . $collectiveId . '/pages/trash/' . $pageId);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user deletes page :page from trash in :collective
	 * @When user :user :fails to delete page :page from trash in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ($fail === 'fails') {
			$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/pages/trash/' . 1);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page);
			$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/pages/trash/' . $pageId);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user deletes template :template from :collective
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesTemplate(string $user, string $template, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$templateId = $this->templateIdByName($collectiveId, $template);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/pages/templates/' . $templateId);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user touches page :page in :collective
	 * @When user :user :fails to touch page :page in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userTouchesPage(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/touch');
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user moves page :page to :newtitle with parentPath :parentPath in :collective
	 * @When user :user :fails to move page :page to :newtitle with parentPath :parentPath in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userMovesPage(string $user, string $page, string $newtitle, string $parentPath, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$parentId = $this->getParentId($collectiveId, $parentPath);
		$formData = new TableNode([['parentId', $parentId], ['title', $newtitle]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId, $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user copies page :page to :newtitle with parentPath :parentPath in :collective
	 * @When user :user :fails to copy page :page to :newtitle with parentPath :parentPath in :collective
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
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId, $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When user :user renames template :template to :newtitle in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userRenamesTemplate(string $user, string $template, string $newtitle, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$templateId = $this->templateIdByName($collectiveId, $template);
		$formData = new TableNode([
			['title', $newtitle],
		]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/templates/' . $templateId, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user moves page :page from collective :oldCollectiveId to collective :newCollectiveId
	 * @When user :user moves page :page from collective :oldCollectiveId to collective :newCollectiveId with parentPath :parentPath
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
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $oldCollectiveId . '/pages/' . $pageId . '/to/' . $newCollectiveId, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user copies page :page from collective :oldCollectiveId to collective :newCollectiveId
	 * @When user :user copies page :page from collective :oldCollectiveId to collective :newCollectiveId with parentPath :parentPath
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
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $oldCollectiveId . '/pages/' . $pageId . '/to/' . $newCollectiveId, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user sets emoji for page :page to :emoji in :collective
	 * @When user :user :fails to set emoji for page :page to :emoji in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPageEmoji(string $user, string $page, string $emoji, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/emoji', $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'emoji', $emoji);
		}
	}

	/**
	 * @When user :user sets emoji for template :template to :emoji in :collective
	 * @When user :user :fails to set emoji for template :template to :emoji in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsTemplateEmoji(string $user, string $template, string $emoji, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$templateId = $this->templateIdByName($collectiveId, $template);
		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $templateId . '/emoji', $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($templateId, 'emoji', $emoji);
		}
	}

	/**
	 * @When user :user sets full width for page :page to :fullWidth in :collective
	 * @When user :user :fails to set full width for page :page to :fullWidth in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsFullWidth(string $user, string $page, string $fullWidth, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$isFullWidth = $fullWidth === 'true';
		$formData = new TableNode([['fullWidth', $isFullWidth]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/fullWidth', $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'isFullWidth', $isFullWidth);
		}
	}

	/**
	 * @When user :user sets subpageOrder for page :page to :subpageOrder in :collective
	 * @When user :user :fails to set subpageOrder for page :page to :subpageOrder in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPageSubpageOrder(string $user, string $page, string $subpageOrder, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$formData = new TableNode([['subpageOrder', $subpageOrder]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/subpageOrder', $formData);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$this->assertPageKeyValue($pageId, 'subpageOrder', json_decode($subpageOrder, true, 512, JSON_THROW_ON_ERROR));
		}
	}

	/**
	 * @When user :user gets setting :key with value :value
	 *
	 * @throws GuzzleException
	 */
	public function userGetsSetting(string $user, string $key, ?string $value = null): string {
		$this->setCurrentUser($user);

		$this->sendOcsCollectivesRequest('GET', 'settings/user/' . $key);
		$this->assertStatusCode(200);

		$jsonBody = $this->getJson();
		if ($value) {
			Assert::assertEquals($value, $jsonBody['ocs']['data'][$key]);
		}

		return $jsonBody['ocs']['data'][$key];
	}

	/**
	 * @When user :user sets setting :key to value :value
	 * @When user :user :fails to set setting :key to value :value
	 *
	 * @throws GuzzleException
	 */
	public function userSetsSetting(string $user, string $key, string $value, ?string $fails = null): void {
		$this->setCurrentUser($user);

		$data = new TableNode([
			['key', $key],
			['value', $value],
		]);
		$this->sendOcsCollectivesRequest('POST', 'settings/user', $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(400);
		}
	}

	/**
	 * @When we wait for :seconds seconds
	 */
	public function waitSeconds(int $seconds): void {
		sleep($seconds);
	}

	/**
	 * @When user :user creates session for :collective
	 * @When user :user :fails to create session for :collective
	 */
	public function userCreatesSession(string $user, string $collective, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/sessions');
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
			$jsonBody = $this->getJson();
			$this->store['sessionToken'] = $jsonBody['ocs']['data']['token'];
		} else {
			$this->assertStatusCode(404);
		}
	}

	/**
	 * @When user :user updates session for :collective
	 * @When user :user updates session for :collective with token :token
	 * @When user :user :fails to update session for :collective
	 * @When user :user :fails to update session for :collective with token :token
	 */
	public function userUpdatesSession(string $user, string $collective, ?string $fails = null, ?string $token = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ($token === null) {
			Assert::assertArrayHasKey('sessionToken', $this->store);
			Assert::assertNotEmpty($this->store['sessionToken']);
			$token = $this->store['sessionToken'];
		}
		$data = new TableNode([
			['token', $token],
		]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/sessions', $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(404);
		}
	}

	/**
	 * @When user :user closes session for :collective
	 * @When user :user closes session for :collective with token :token
	 * @When user :user :fails to close session for :collective
	 * @When user :user :fails to close session for :collective with token :token
	 */
	public function userClosesSession(string $user, string $collective, ?string $fails = null, ?string $token = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		if ($token === null) {
			Assert::assertArrayHasKey('sessionToken', $this->store);
			Assert::assertNotEmpty($this->store['sessionToken']);
			$token = $this->store['sessionToken'];
		}
		$data = new TableNode([
			['token', $token],
		]);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/sessions', $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(404);
		}
	}

	/**
	 * @When user :user creates tag :tag with color :color for collective :collective
	 * @When user :user :fails to create tag :tag with color :color for collective :collective
	 * @When user :user :fails to create :existing tag :tag with color :color for collective :collective
	 */
	public function userCreatesTag(string $user, string $collective, string $tag, string $color, ?string $fails = null, ?string $existing = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$data = new TableNode([
			['name', $tag],
			['color', $color],
		]);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/tags', $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			if ($existing !== null) {
				$this->assertStatusCode(400);
			} else {
				$this->assertStatusCode(403);
			}
		}
	}

	/**
	 * @When user :user updates tag :tag with color :color for collective :collective
	 * @When user :user :fails to update tag :tag with color :color for collective :collective
	 */
	public function userUpdatesTag(string $user, string $collective, string $tag, string $color, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$id = $this->tagIdByName($collectiveId, $tag);
		$data = new TableNode([
			['name', $tag],
			['color', $color],
		]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/tags/' . $id, $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When user :user deletes tag :tag for collective :collective
	 * @When user :user :fails to delete tag :tag for collective :collective
	 */
	public function userDeletesTag(string $user, string $collective, string $tag, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$id = $this->tagIdByName($collectiveId, $tag);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/tags/' . $id);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When user :user sees tag :tag with color :color for collective :collective
	 * @When user :user :fails to see tag :tag for collective :collective
	 * @When user :user :fails to see tag :tag with color :color for collective :collective
	 */
	public function userSeesTag(string $user, string $collective, string $tag, ?string $color = null, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$id = $this->tagIdByName($collectiveId, $tag, $color);
		if ($fails !== 'fails') {
			Assert::assertNotNull($id);
		} else {
			Assert::assertNull($id);
		}
	}

	/**
	 * @When user :user adds tag :tag to page :page in collective :collective
	 * @When user :user :fails to add tag :tag to page :page in collective :collective
	 * @When user :user :fails to add tagId :tagId to page :page in collective :collective
	 */
	public function userAddsTagToPage(string $user, string $collective, string $page, ?string $tag = null, ?string $tagId = null, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		if ($tag) {
			$tagId = $this->tagIdByName($collectiveId, $tag);
		}
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/tags/' . $tagId);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} elseif (!$tag) {
			// Trying to add a non-existent tag
			$this->assertStatusCode(404);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When user :user removes tag :tag from page :page in collective :collective
	 * @When user :user :fails to remove tag :tag from page :page in collective :collective
	 */
	public function userRemovesTagFromPage(string $user, string $collective, string $page, string $tag, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$tagId = $this->tagIdByName($collectiveId, $tag);
		$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/tags/' . $tagId);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When user :user joins team :name with owner :owner
	 * @When user :user joins team :name with owner :owner with level :level
	 *
	 * @throws GuzzleException
	 */
	public function userJoinsTeam(string $user, string $name, string $owner, ?string $level = null): void {
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
	 * @When user :user leaves team :name with owner :owner
	 */
	public function userLeavesTeam(string $user, string $name, string $owner): void {
		$this->setCurrentUser($owner);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
		$memberId = $this->circleMemberIdByName($circleId, $user);
		Assert::assertNotNull($memberId);

		$this->sendOcsRequest('DELETE', '/apps/circles/circles/' . $circleId . '/members/' . $memberId);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user is member of team :name
	 *
	 * @throws GuzzleException
	 */
	public function userIsMemberOfTeam(string $user, string $name): void {
		$this->setCurrentUser($user);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
	}

	/**
	 * @When user :user deletes team :name
	 * @When user :user :fails to delete team :name
	 *
	 * @throws GuzzleException
	 */
	public function userDeletesTeam(string $user, string $name, ?string $fails = null): void {
		$this->setCurrentUser($user);
		$circleId = $this->circleIdByName($name);
		Assert::assertNotNull($circleId);
		$this->sendOcsRequest('DELETE', '/apps/circles/circles/' . $circleId);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(400);
		}
	}

	/**
	 * @When app :appId is :status
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
	 * @throws GuzzleException
	 */
	public function userCreatesPublicShare(string $user, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/shares');
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$jsonBody = $this->getJson();
			Assert::assertNotEmpty($jsonBody['ocs']['data']['token']);
		}
	}

	/**
	 * @When user :user creates public page share for page :page in :collective
	 * @When user :user :fails to create public page share for page :page in :collective
	 *
	 * @throws GuzzleException
	 */
	public function userCreatesPublicPageShare(string $user, string $collective, string $page, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendOcsCollectivesRequest('POST', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/shares');
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
			$jsonBody = $this->getJson();
			Assert::assertNotEmpty($jsonBody['ocs']['data']['token']);
		}
	}

	/**
	 * @When user :user sets editing permissions for collective share :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPublicShareEditPermissions(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$formData = new TableNode([['editable', true]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/shares/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user sets editing permissions for page share :page in collective :collective
	 *
	 * @throws GuzzleException
	 */
	public function userSetsPublicFileShareEditPermissions(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$token = $this->getShareToken($collectiveId, $pageId);
		$formData = new TableNode([['editable', true]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/shares/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user unsets editing permissions for collective share :collective
	 *
	 * @throws GuzzleException
	 */
	public function userUnsetsPublicShareEditPermissions(string $user, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$formData = new TableNode([['editable', false]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/shares/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user unsets editing permissions for page share :page in collective :collective
	 *
	 * @throws GuzzleException
	 */
	public function userUnsetsPublicFileShareEditPermissions(string $user, string $page, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$token = $this->getShareToken($collectiveId, $pageId);
		$formData = new TableNode([['editable', false]]);
		$this->sendOcsCollectivesRequest('PUT', 'collectives/' . $collectiveId . '/pages/' . $pageId . '/shares/' . $token, $formData);
		$this->assertStatusCode(200);
	}

	/**
	 * @When user :user stores token for public share :collective
	 * @When user :user stores token for public page share :pageShare in collective :collective
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
			$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/pages/' . $pageShareId . '/shares/' . $token);
		} else {
			$this->sendOcsCollectivesRequest('DELETE', 'collectives/' . $collectiveId . '/shares/' . $token);
		}
		$this->assertStatusCode(200);
	}

	/**
	 * @When anonymous sees public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicCollective(string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token, null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertCollectiveByName($collective);
		$this->assertCollectiveLevel($collective, 1);
	}

	/**
	 * @When anonymous sees public page share :page in collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicPageShare(string $page, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$token = $this->getShareToken($collectiveId, $pageId);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token, null, null, [], false);
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
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $this->store['shareToken'], null, null, [], false);
		$this->assertStatusCode(404);
	}

	/**
	 * @When anonymous sees pagePath :path in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicCollectivePages(string $path, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token . '/pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path);
	}

	/**
	 * @When anonymous sees pagePath :path in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPublicPagePages(string $path, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token . '/pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path);
	}

	/**
	 * @When anonymous doesn't see pagePath :path in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDoesntSeePublicCollectivePages(string $path, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token . '/pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path, true);
	}

	/**
	 * @When anonymous doesn't see pagePath :path in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDoesntSeePublicPageSharePages(string $path, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token . '/pages', null, null, [], false);
		$this->assertStatusCode(200);
		$this->assertPageByPath($path, true);
	}

	/**
	 * @When anonymous creates page :page with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to create page :page with parentPath :parentPath in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousCreatesPublicCollectivePage(string $page, string $parentPath, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$parentId = $this->getParentId($collectiveId, $parentPath);

		$formData = new TableNode([['title', $page]]);
		$this->sendOcsCollectivesRequest('POST', 'p/collectives/' . $token . '/pages/' . $parentId, $formData, null, [], false);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous creates page :page with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
	 * @When anonymous :fails to create page :page with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
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
		$this->sendOcsCollectivesRequest('POST', 'p/collectives/' . $token . '/pages/' . $parentId, $formData, null, [], false);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous moves page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to move page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
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
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/pages/' . $pageId, $formData, null, [], false);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous moves page :page to :newtitle with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
	 * @When anonymous :fails to move page :page to :newtitle with parentPath :parentPath in public page share :pageShare in collective :collective with owner :owner
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
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/pages/' . $pageId, $formData, null, [], false);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous copies page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
	 * @When anonymous :fails to copy page :page to :newtitle with parentPath :parentPath in public collective :collective with owner :owner
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
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/pages/' . $pageId, $formData, null, [], false);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous sets emoji for page :page to :emoji in public collective :collective with owner :owner
	 * @When anonymous :fails to set emoji for page :page to :emoji in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSetsPublicCollectivePageEmoji(string $page, string $emoji, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/pages/' . $pageId . '/emoji', $formData);
		if ($fail === 'fails') {
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
	 * @throws GuzzleException
	 */
	public function anonymousSetsPublicPageSharePageEmoji(string $page, string $emoji, string $pageShare, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$formData = new TableNode([['emoji', $emoji]]);
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/pages/' . $pageId . '/emoji', $formData);
		if ($fail === 'fails') {
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
	 * @throws GuzzleException
	 */
	public function anonymousTrashesPublicCollectivePage(string $page, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$this->sendOcsCollectivesRequest('DELETE', 'p/collectives/' . $token . '/pages/' . $pageId, null, null, [], false);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous fails to trash page :page in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousTrashesPublicPageSharePage(string $page, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);

		$this->sendOcsCollectivesRequest('DELETE', 'p/collectives/' . $token . '/pages/' . $pageId, null, null, [], false);
		$this->assertStatusCode(403);
	}

	/**
	 * @When anonymous restores page :page from trash in public collective :collective with owner :owner
	 * @When anonymous :fails to restore page :page from trash in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousRestoresPublicCollectivePage(string $page, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);

		if ($fail === 'fails') {
			$this->sendOcsCollectivesRequest('PATCH', 'p/collectives/' . $token . '/pages/trash/1', null, null, [], false);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page, $token);
			$this->sendOcsCollectivesRequest('PATCH', 'p/collectives/' . $token . '/pages/trash/' . $pageId, null, null, [], false);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @When anonymous deletes page :page from trash in public collective :collective with owner :owner
	 * @When anonymous :fails to delete page :page from trash in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousDeletesPublicCollectivePage(string $page, string $collective, string $owner, ?string $fail = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);

		if ($fail === 'fails') {
			$this->sendOcsCollectivesRequest('DELETE', 'p/collectives/' . $token . '/pages/trash/1', null, null, [], false);
			$this->assertStatusCode(403);
		} else {
			$pageId = $this->trashedPageIdByName($collectiveId, $page, $token);
			$this->sendOcsCollectivesRequest('DELETE', 'p/collectives/' . $token . '/pages/trash/' . $pageId, null, null, [], false);
			$this->assertStatusCode(200);
		}
	}

	/**
	 * @Then anonymous sees attachment :name with mimetype :mimetype for :page in public collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesAttachments(string $name, string $mimetype, string $page, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token . '/pages/' . $pageId . '/attachments');
		$this->assertStatusCode(200);
		$this->assertAttachment($name, $mimetype);
	}

	/**
	 * @Then anonymous sees attachment :name with mimetype :mimetype for :page in public page share :pageShare in collective :collective with owner :owner
	 *
	 * @throws GuzzleException
	 */
	public function anonymousSeesPageShareAttachments(string $name, string $mimetype, string $page, string $pageShare, string $collective, string $owner): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageShareId = $this->pageIdByName($collectiveId, $pageShare);
		$token = $this->getShareToken($collectiveId, $pageShareId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$this->sendOcsCollectivesRequest('GET', 'p/collectives/' . $token . '/pages/' . $pageId . '/attachments');
		$this->assertStatusCode(200);
		$this->assertAttachment($name, $mimetype);
	}

	/**
	 * @When anonymous creates tag :tag with color :color for collective :collective with owner :owner
	 * @When anonymous :fails to create tag :tag with color :color for collective :collective with owner :owner
	 * @When anonymous :fails to create :existing tag :tag with color :color for collective :collective with owner :owner
	 */
	public function anonymousCreatesTag(string $collective, string $tag, string $color, string $owner, ?string $fails = null, ?string $existing = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$data = new TableNode([
			['name', $tag],
			['color', $color],
		]);
		$this->sendOcsCollectivesRequest('POST', 'p/collectives/' . $token . '/tags', $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			if ($existing !== null) {
				$this->assertStatusCode(400);
			} else {
				$this->assertStatusCode(403);
			}
		}
	}

	/**
	 * @When anonymous updates tag :tag with color :color for collective :collective with owner :owner
	 * @When anonymous :fails to update tag :tag with color :color for collective :collective with owner :owner
	 */
	public function anonymousUpdatesTag(string $collective, string $tag, string $color, string $owner, ?string $fails = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$id = $this->tagIdByName($collectiveId, $tag, null, $token);
		$data = new TableNode([
			['name', $tag],
			['color', $color],
		]);
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/tags/' . $id, $data);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When anonymous deletes tag :tag for collective :collective with owner :owner
	 * @When anonymous :fails to delete tag :tag for collective :collective with owner :owner
	 */
	public function anonymousDeletesTag(string $collective, string $tag, string $owner, ?string $fails = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$id = $this->tagIdByName($collectiveId, $tag, null, $token);
		$this->sendOcsCollectivesRequest('DELETE', 'p/collectives/' . $token . '/tags/' . $id);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When anonymous sees tag :tag with color :color for collective :collective with owner :owner
	 * @When anonymous :fails to see tag :tag for collective :collective with owner :owner
	 * @When anonymous :fails to see tag :tag with color :color for collective :collective with owner :owner
	 */
	public function anonymousSeesTag(string $collective, string $tag, string $owner, ?string $color = null, ?string $fails = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$id = $this->tagIdByName($collectiveId, $tag, $color, $token);
		if ($fails !== 'fails') {
			Assert::assertNotNull($id);
		} else {
			Assert::assertNull($id);
		}
	}

	/**
	 * @When anonymous adds tag :tag to page :page in collective :collective with owner :owner
	 * @When anonymous :fails to add tag :tag to page :page in collective :collective with owner :owner
	 * @When anonymous :fails to add tagId :tagId to page :page in collective :collective with owner :owner
	 */
	public function anonymousAddsTagToPage(string $collective, string $page, string $owner, ?string $tag = null, ?string $tagId = null, ?string $fails = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		if ($tag) {
			$tagId = $this->tagIdByName($collectiveId, $tag);
		}
		$this->sendOcsCollectivesRequest('PUT', 'p/collectives/' . $token . '/pages/' . $pageId . '/tags/' . $tagId);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} elseif (!$tag) {
			// Trying to add a non-existent tag
			$this->assertStatusCode(404);
		} else {
			$this->assertStatusCode(403);
		}
	}

	/**
	 * @When anonymous removes tag :tag from page :page in collective :collective with owner :owner
	 * @When anonymous :fails to remove tag :tag from page :page in collective :collective with owner :owner
	 */
	public function anonymousRemovesTagFromPage(string $collective, string $page, string $owner, string $tag, ?string $fails = null): void {
		$this->setCurrentUser($owner);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$tagId = $this->tagIdByName($collectiveId, $tag);
		$this->sendOcsCollectivesRequest('DELETE', 'p/collectives/' . $token . '/pages/' . $pageId . '/tags/' . $tagId);
		if ($fails !== 'fails') {
			$this->assertStatusCode(200);
		} else {
			$this->assertStatusCode(403);
		}
	}

	private function getUserCollectivesPath(string $user): string {
		$this->setCurrentUser($user);
		return $this->userGetsSetting($user, 'user_folder');
	}

	/**
	 * @When user :user has webdav access to :collective with permissions :permissions
	 *
	 * @throws GuzzleException
	 */
	public function hasWebdavAccess(string $collective, string $user, string $permissions): void {
		$this->setCurrentUser($user);
		$headers = [
			'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
			'Depth' => 0,
		];
		$dom = new DOMDocument('1.0', 'UTF-8');
		$xPropfind = $dom->createElementNS('DAV:', 'D:propfind');
		$xProp = $dom->createElement('D:prop');
		$xProp->setAttribute('xmlns:oc', 'http://owncloud.org/ns');
		$xProp->appendChild($dom->createElement('oc:permissions'));
		$dom->appendChild($xPropfind)->appendChild($xProp);
		$body = $dom->saveXML();
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		$this->sendRemoteRequest('PROPFIND', '/dav/files/' . $user . $userCollectivesPath . '/' . urlencode($collective) . '/', $body, null, $headers);
		$this->assertStatusCode(207);

		// simplexml_load_string() would be better than preg_replace
		$folderPermissions = preg_replace('/.*<oc:permissions>(.*)<\/oc:permissions>.*/sm', '\1', $this->response->getBody()->getContents());

		Assert::assertEquals($permissions, $folderPermissions);
	}

	/**
	 * @When public share with owner :user has webdav access to :collective with permissions :permissions
	 *
	 * @throws GuzzleException
	 */
	public function hasPublicWebdavAccess(string $collective, string $user, string $permissions): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$token = $this->getShareToken($collectiveId);
		$headers = [
			'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
			'Depth' => 0,
		];
		$dom = new DOMDocument('1.0', 'UTF-8');
		$xPropfind = $dom->createElementNS('DAV:', 'D:propfind');
		$xProp = $dom->createElement('D:prop');
		$xProp->setAttribute('xmlns:oc', 'http://owncloud.org/ns');
		$xProp->appendChild($dom->createElement('oc:permissions'));
		$dom->appendChild($xPropfind)->appendChild($xProp);
		$body = $dom->saveXML();

		$this->sendPublicRequest('PROPFIND', '/dav/files/' . $token . '/', $body, null, $headers);
		$this->assertStatusCode(207);

		// simplexml_load_string() would be better than preg_replace
		$folderPermissions = preg_replace('/.*<oc:permissions>(.*)<\/oc:permissions>.*/sm', '\1', $this->response->getBody()->getContents());

		Assert::assertEquals($permissions, $folderPermissions);
	}

	/**
	 * @throws GuzzleException
	 */
	private function listWebdavTrash(string $user): array {
		$this->setCurrentUser($user);
		$headers = [
			'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
			'Depth' => 1,
		];
		$dom = new DOMDocument('1.0', 'UTF-8');
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
	 * @When user :user uploads attachment :fileName to :page with file path :filePath in :collective
	 *
	 * @throws GuzzleException
	 */
	public function uploadsAttachment(string $user, string $fileName, string $page, string $filePath, string $collective): void {
		$this->setCurrentUser($user);
		$collectiveId = $this->collectiveIdByName($collective);
		$pageId = $this->pageIdByName($collectiveId, $page);
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		$attachmentsPath = '/dav/files/' . $user . '/' . $userCollectivesPath . '/' . urlencode($collective) . $filePath . '.attachments.' . $pageId;

		$this->sendRemoteRequest('MKCOL', $attachmentsPath);
		$this->assertStatusCode(201);
		$body = fopen('tests/Integration/features/fixtures/' . $fileName, 'rb');
		$this->sendRemoteRequest('PUT', $attachmentsPath . '/' . $fileName, $body);
		$this->assertStatusCode(201);
	}

	/**
	 * @When user :user trashes page :page via webdav in :collective
	 * @When user :user :fails to trash page :page via webdav in :collective
	 *
	 * @throws GuzzleException
	 */
	public function webdavTrashFile(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);
		$userCollectivesPath = $this->getUserCollectivesPath($user);

		$filePath = '/dav/files/' . $user . '/' . $userCollectivesPath . '/' . urlencode($collective) . '/' . urlencode($page) . '.md';

		$this->sendRemoteRequest('DELETE', $filePath);
		if ($fail === 'fails') {
			$this->assertStatusCode(403);
		} else {
			$this->assertStatusCode(204);
		}
	}

	/**
	 * @When user :user restores page :page from trash via webdav in :collective
	 * @When user :user :fails to restore page :page from trash via webdav in :collective
	 *
	 * @throws GuzzleException
	 */
	public function webdavRestoreFile(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);

		$href = $this->inWebdavTrash($collective, urlencode($page) . '.md', $user);
		if ($fail === 'fails') {
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
	 * @throws GuzzleException
	 */
	public function webdavDeleteFile(string $user, string $page, string $collective, ?string $fail = null): void {
		$this->setCurrentUser($user);

		$href = $this->inWebdavTrash($collective, urlencode($page) . '.md', $user);
		if ($fail === 'fails') {
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
	 * @throws JsonException
	 */
	private function getJson(): array {
		if (!$this->json) {
			$this->json = json_decode($this->response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
		}
		return $this->json;
	}

	/**
	 * @throws GuzzleException
	 */
	private function circleIdByName(string $name): ?string {
		$this->sendOcsRequest('GET', '/apps/circles/circles');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of teams');
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
	 * @throws GuzzleException
	 */
	private function circleMemberByUser(string $circleId, string $user): ?string {
		$this->sendOcsRequest('GET', '/apps/circles/circles/' . $circleId . '/members');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of members for team ' . $circleId);
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
	 * @throws GuzzleException
	 */
	private function circleMemberIdByName(string $circleId, string $userId): ?string {
		$this->sendOcsRequest('GET', '/apps/circles/circles/' . $circleId . '/members');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of team members');
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
	 * @throws GuzzleException
	 */
	private function collectiveIdByName(string $name, bool $trash = false): ?int {
		if ($trash) {
			$this->sendOcsCollectivesRequest('GET', 'collectives/trash');
		} else {
			$this->sendOcsCollectivesRequest('GET', 'collectives');
		}
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['collectives'] as $collective) {
			if ($name === $collective['name']) {
				return $collective['id'];
			}
		}
		return null;
	}

	/**
	 * @throws GuzzleException
	 */
	private function pageIdByName(int $collectiveId, string $name): ?int {
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of pages for collective ' . $collectiveId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['pages'] as $page) {
			if ($name === $page['title']) {
				return $page['id'];
			}
		}
		return null;
	}

	private function templateIdByName(int $collectiveId, string $name): ?int {
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages/templates');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of templates for collective ' . $collectiveId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['templates'] as $page) {
			if ($name === $page['title']) {
				return $page['id'];
			}
		}
		return null;
	}

	private function expectTemplateId(int $collectiveId, string $templateName): bool {
		return (bool)$this->templateIdByName($collectiveId, $templateName);
	}

	/**
	 * @throws GuzzleException
	 */
	private function trashedPageIdByName(int $collectiveId, string $name, ?string $token = null): ?int {
		if ($token) {
			$this->sendOcsCollectivesRequest('GET', '/p/collectives/' . $token . '/pages/trash', null, null, [], false);
		} else {
			$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages/trash');
		}
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of trashed pages for collective ' . $collectiveId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['pages'] as $page) {
			if ($name === $page['title']) {
				return $page['id'];
			}
		}
		return null;
	}

	private function tagIdByName(int $collectiveId, string $name, ?string $color = null, ?string $token = null): ?int {
		if ($token) {
			$this->sendOcsCollectivesRequest('GET', '/p/collectives/' . $token . '/tags', null, null, [], false);
		} else {
			$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/tags');
		}
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of tags for collective ' . $collectiveId);
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['tags'] as $tag) {
			if ($name === $tag['name']) {
				if ($color !== null && $color !== $tag['color']) {
					return null;
				}
				return $tag['id'];
			}
		}
		return null;
	}

	/**
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
	 * @param string|resource|null $body
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
		if (str_contains($fullUrl, '?')) {
			$fullUrl .= '&' . $ocsJsonFormat;
		} else {
			$fullUrl .= '?' . $ocsJsonFormat;
		}
		$this->sendRequestBase($verb, $fullUrl, $body, $jsonBody, $headers, $auth);
	}

	private function sendOcsCollectivesRequest(string $verb,
		string $url,
		?TableNode $body = null,
		?array $jsonBody = null,
		array $headers = [],
		?bool $auth = true): void {
		$this->sendOcsRequest($verb, self::API_URL . $url, $body, $jsonBody, $headers, $auth);
	}

	private function sendPublicRequest(string $verb,
		string $url,
		$body = null,
		?array $jsonBody = null,
		array $headers = []) {
		$fullUrl = $this->publicUrl . $url;
		$this->sendRequestBase($verb, $fullUrl, $body, $jsonBody, $headers, false);
	}

	/**
	 * @param TableNode|string|null $body
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

		if ($auth === true && !isset($this->cookieJars[$this->currentUser])) {
			$this->cookieJars[$this->currentUser] = new CookieJar();
		}

		// Get request token for user (required due to CSRF checks)
		if ($auth === true && !isset($this->requestTokens[$this->currentUser])) {
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
		if ($this->setXdebugSession) {
			$xdebugSession = 'XDEBUG_SESSION=PHPSTORM';
			if (str_contains($url, '?')) {
				$url .= '&' . $xdebugSession;
			} else {
				$url .= '?' . $xdebugSession;
			}
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

	private function getNextcloudVersion(bool $major = false): string {
		if ($this->nextcloudVersion === null) {
			$headers = [
				'Content-Type' => 'Content-Type: text/xml; charset="utf-8"',
				'Depth' => 0,
			];

			$statusUrl = str_replace('index.php', 'status.php', $this->baseUrl);
			$this->sendRequestBase('GET', $statusUrl, null, null, $headers, false);
			$this->assertStatusCode(200);

			$this->nextcloudVersion = $this->getJson()['versionstring'];
		}

		return $major ? explode('.', $this->nextcloudVersion)[0] : $this->nextcloudVersion;
	}

	/**
	 * @throws GuzzleException
	 */
	private function getUserRequestToken(string $user): void {
		$loginUrl = $this->baseUrl . '/login';

		if (!isset($this->requestTokens[$user])) {
			// Request a new session and extract CSRF token
			$client = new Client($this->clientOptions);
			$response = $client->get(
				$loginUrl,
				[
					'cookies' => $this->cookieJars[$user],
					'headers' => [
						'Origin' => $this->baseUrl,
					],
				],
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
					'headers' => [
						'Origin' => $this->baseUrl,
					],
				]
			);
			$this->assertStatusCode(200);

			$this->requestTokens[$user] = substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $this->response->getBody()->getContents()), 0, 89);
		}
	}

	private function getUserLanguage(string $user): string {
		$this->sendOcsRequest('GET', '/cloud/users/' . $user);
		$this->assertStatusCode(200);

		$jsonBody = $this->getJson();
		return $jsonBody['ocs']['data']['language'];
	}

	private function setCurrentUser(string $user): void {
		$this->currentUser = $user;
	}

	private function getParentId(int $collectiveId, string $parentPath): int {
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/pages');
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['pages'] as $page) {
			$path = $page['filePath'] ? $page['filePath'] . '/' . $page['fileName'] : $page['fileName'];
			if ($parentPath === $path) {
				return $page['id'];
			}
		}
		throw new RuntimeException('Could not get parent page id for ' . $parentPath);
	}

	private function getShareToken(int $collectiveId, int $pageId = 0): ?string {
		$this->sendOcsCollectivesRequest('GET', 'collectives/' . $collectiveId . '/shares');
		if ($this->response->getStatusCode() !== 200) {
			throw new RuntimeException('Unable to get list of collectives');
		}
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data'] as $share) {
			if ($collectiveId === $share['collectiveId'] && $pageId === $share['pageId']) {
				return $share['token'];
			}
		}
		return null;
	}

	private function assertStatusCode(mixed $statusCode, string $message = ''): void {
		if (is_int($statusCode)) {
			$message = $message ?: 'Status code ' . $this->response->getStatusCode() . ' is not expected ' . $statusCode . '.';
			Assert::assertEquals($statusCode, $this->response->getStatusCode(), $message);
		} elseif (is_array($statusCode)) {
			$message = $message ?: 'Status code ' . $this->response->getStatusCode() . ' is neither of ' . implode(', ', $statusCode) . '.';
			Assert::assertContains($this->response->getStatusCode(), $statusCode, $message);
		}
	}

	private function assertCollectiveByName(string $name, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		$collectiveNames = [];
		foreach ($jsonBody['ocs']['data']['collectives'] as $collective) {
			$collectiveNames[] = $collective['name'];
		}
		if ($revert === false) {
			Assert::assertContains($name, $collectiveNames);
		} else {
			Assert::assertNotContains($name, $collectiveNames);
		}
	}


	private function assertCollectiveKeyValue(string $name, string $key, string $value, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		foreach ($jsonBody['ocs']['data']['collectives'] as $c) {
			if ($c['name'] === $name) {
				$collective = $c;
			}
		}

		if (!isset($collective)) {
			throw new RuntimeException('Unable to find collective ' . $name);
		}

		if ($revert === false) {
			Assert::assertEquals($value, json_encode($collective[$key]));
		} else {
			Assert::assertNotEquals($value, $collective[$key]);
		}
	}

	private function assertCollectiveLevel(string $name, int $level): void {
		$data = $this->getJson()['ocs']['data'];
		// Dirty hack. We don't know whether $data contains the collective (e.g. after collective#create)
		// or an array of collectives (e.g. after collectives#index or publicCollectives#get)
		if (array_key_exists('collective', $data)) {
			$collective = $data['collective'];
		} else {
			$collective = $data['collectives'][0];
		}
		Assert::assertEquals($name, $collective['name']);
		Assert::assertEquals($level, $collective['level']);
	}

	private function assertPageByPath(string $path, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		$pagePaths = [];
		foreach ($jsonBody['ocs']['data']['pages'] as $page) {
			$pagePaths[] = $page['filePath'] ? $page['filePath'] . '/' . $page['fileName'] : $page['fileName'];
		}
		if ($revert === false) {
			Assert::assertContains($path, $pagePaths);
		} else {
			Assert::assertNotContains($path, $pagePaths);
		}
	}

	private function assertPageKeyValue(int $id, string $key, mixed $value, ?bool $revert = false): void {
		$jsonBody = $this->getJson();
		$page = $jsonBody['ocs']['data']['page'];

		if (!isset($page)) {
			throw new RuntimeException('Unable to find page with ID ' . $id);
		}

		if ($revert === false) {
			Assert::assertEquals($value, $page[$key]);
		} else {
			Assert::assertNotEquals($value, $page[$key]);
		}
	}

	private function assertPageLastEditedByUser(string $title, string $user): void {
		$jsonBody = $this->getJson();
		$pageTitles = [];
		foreach ($jsonBody['ocs']['data']['pages'] as $page) {
			if ($page['lastUserId'] === $user) {
				$pageTitles[] = $page['title'];
			}
		}
		Assert::assertContains($title, $pageTitles);
	}

	private function assertAttachment(string $name, string $mimetype): void {
		$jsonBody = $this->getJson();
		$attachment = [
			'name' => $name,
			'mimetype' => $mimetype,
		];

		$pageAttachments = array_map(static fn ($attachment) => [
			'name' => $attachment['name'],
			'mimetype' => $attachment['mimetype'],
		], $jsonBody['ocs']['data']['attachments']);

		Assert::assertContains($attachment, $pageAttachments);
	}
}
