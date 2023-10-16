<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Db\CollectiveShareMapper;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\PublicShareController;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\ISession;

class PublicStartController extends PublicShareController {
	private CollectiveShareMapper $collectiveShareMapper;
	private IAppManager $appManager;
	private IEventDispatcher $eventDispatcher;

	public function __construct(string $AppName,
		IRequest $request,
		ISession $session,
		CollectiveShareMapper $collectiveShareMapper,
		IAppManager $appManager,
		IEventDispatcher $eventDispatcher
	) {
		parent::__construct($AppName, $request, $session);
		$this->collectiveShareMapper = $collectiveShareMapper;
		$this->appManager = $appManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @return string
	 */
	protected function getPasswordHash(): string {
		return '';
	}

	/**
	 * @return bool
	 */
	public function isValidToken(): bool {
		try {
			$this->collectiveShareMapper->findOneByToken($this->getToken());
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return false;
		}

		return true;
	}

	/**
	 * @return bool
	 */
	protected function isPasswordProtected(): bool {
		return false;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @param string $path
	 *
	 * @return PublicTemplateResponse
	 */
	public function publicIndex(string $token, string $path): PublicTemplateResponse {
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

	/**
	 * @return array
	 */
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
