<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\AuthPublicShareController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as ShareManager;
use OCP\Share\IShare;

class PublicStartController extends AuthPublicShareController {
	protected ?IShare $share = null;

	public function __construct(string $AppName,
		IRequest $request,
		ISession $session,
		IURLGenerator $urlGenerator,
		private ShareManager $shareManager,
		private CollectiveShareMapper $collectiveShareMapper,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher) {
		parent::__construct($AppName, $request, $session, $urlGenerator);
	}

	/**
	 * @throws ShareNotFound
	 */
	protected function getShare(): IShare {
		if ($this->share === null) {
			$this->share = $this->shareManager->getShareByToken($this->getToken());
		}
		return $this->share;
	}

	protected function getPasswordHash(): string {
		return $this->getShare()->getPassword();
	}

	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException | MultipleObjectsReturnedException) {
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
		// TODO: use `OCA\DAV\Connector\Sabre\PublicAuth::DAV_AUTHENTICATED` (only in stable29+)
		$this->session->set('public_link_authenticated', $this->getShare()->getId());
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function showAuthenticate(): TemplateResponse {
		$templateParameters = ['share' => $this->getShare()];
		return new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function showAuthFailed(): TemplateResponse {
		$templateParameters = ['share' => $this->getShare(), 'wrongpw' => true];
		return new TemplateResponse('core', 'publicshareauth', $templateParameters, 'guest');
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 */
	public function showShare(): PublicTemplateResponse {
		if ($appsMissing = $this->checkDependencies()) {
			return new PublicTemplateResponse('collectives', 'error', ['appsMissing' => $appsMissing]);  // templates/error.php
		}
		$this->eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());
		$response = new PublicTemplateResponse('collectives', 'main', [ // templates/main.php
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);
		$response->setFooterVisible(false);
		return $response;
	}

	private function checkDependencies(): array {
		$apps = ['circles', 'files_versions', 'text', 'viewer'];
		$appsMissing = [];
		foreach ($apps as $app) {
			if (!$this->appManager->isEnabledForUser($app)) {
				$appsMissing[] = $app;
			}
		}
		return $appsMissing;
	}
}
