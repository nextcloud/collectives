<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Collectives\Service\CollectiveService;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCA\DAV\Connector\Sabre\PublicAuth;
use OCA\Files_Sharing\Event\ShareLinkAccessedEvent;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\Template\SimpleMenuAction;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class PublicStartController extends AuthPublicShareController {
	protected ?IShare $share = null;

	public function __construct(
		string $appName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		private readonly ShareManager $shareManager,
		private readonly CollectiveShareMapper $collectiveShareMapper,
		private readonly CollectiveService $collectiveService,
		private readonly IAppManager $appManager,
		private readonly IEventDispatcher $eventDispatcher,
		private readonly IL10N $l10n,
	) {
		parent::__construct($appName, $request, $session, $urlGenerator);
	}

	/**
	 * @throws ShareNotFound
	 */
	protected function getShare(): IShare {
		$this->share ??= $this->shareManager->getShareByToken($this->getToken());
		return $this->share;
	}

	/**
	 * @psalm-suppress InvalidNullableReturnType
	 * @psalm-suppress NullableReturnStatement
	 */
	protected function getPasswordHash(): string {
		return $this->getShare()->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return false;
		}

		return true;
	}

	protected function isPasswordProtected(): bool {
		return $this->getShare()->getPassword() !== null;
	}

	protected function verifyPassword(string $password): bool {
		return $this->shareManager->checkPassword($this->getShare(), $password);
	}

	protected function authSucceeded() {
		$allowedShareIds = $this->session->get(PublicAuth::DAV_AUTHENTICATED);
		if (!is_array($allowedShareIds)) {
			$allowedShareIds = [];
		}
		$this->session->set(PublicAuth::DAV_AUTHENTICATED, array_merge($allowedShareIds, [$this->getShare()->getId()]));
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function showAuthenticate(): TemplateResponse {
		$templateParameters = ['share' => $this->getShare()];
		return new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function showAuthFailed(): TemplateResponse {
		$templateParameters = ['share' => $this->getShare(), 'wrongpw' => true];
		return new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[AnonRateLimit(limit: 10, period: 60)]
	public function showShare(): PublicTemplateResponse {
		if ($appsMissing = $this->checkDependencies()) {
			return new PublicTemplateResponse('collectives', 'error', ['appsMissing' => $appsMissing]);  // templates/error.php
		}
		// The Viewer app on Nextcloud 35 and older, newer versions have the viewer in server
		if (class_exists(LoadViewer::class)) {
			$this->eventDispatcher->dispatchTyped(new LoadViewer());
		}
		$this->eventDispatcher->dispatchTyped(new ShareLinkAccessedEvent($this->getShare(), 'show'));
		$response = new PublicTemplateResponse('collectives', 'main', [ // templates/main.php
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
			'token' => $this->getToken(),
		]);
		$response->setFooterVisible(false);
		$this->setHeader($response);
		return $response;
	}

	private function setHeader(PublicTemplateResponse $response): void {
		try {
			$share = $this->collectiveShareMapper->findOneByToken($this->getToken());
			$collective = $this->collectiveService->getCollective($share->getCollectiveId(), $share->getOwner());
		} catch (DoesNotExistException|MultipleObjectsReturnedException|NotFoundException|NotPermittedException|MissingDependencyException) {
			return;
		}

		$emoji = $collective->getEmoji();
		$response->setHeaderTitle($emoji ? $emoji . ' ' . $collective->getName() : $collective->getName());
		$response->setHeaderActions([
			new SimpleMenuAction(
				'print',
				$this->l10n->t('Export or print'),
				'icon-download',
				$this->urlGenerator->linkToRoute('collectives.publicStart.showShare', [
					'token' => $this->getToken(),
					'path' => 'print/' . $collective->getUrlPath(),
				]),
			),
		]);
	}

	private function checkDependencies(): array {
		$apps = ['circles', 'files_versions', 'text'];
		$appsMissing = [];
		foreach ($apps as $app) {
			if (!$this->appManager->isEnabledForUser($app)) {
				$appsMissing[] = $app;
			}
		}
		return $appsMissing;
	}
}
