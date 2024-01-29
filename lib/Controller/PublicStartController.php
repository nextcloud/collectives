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
	public function __construct(string $AppName,
		IRequest $request,
		ISession $session,
		private CollectiveShareMapper $collectiveShareMapper,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher) {
		parent::__construct($AppName, $request, $session);
	}

	protected function getPasswordHash(): string {
		return '';
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
		return false;
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
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
