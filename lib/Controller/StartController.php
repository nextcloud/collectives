<?php

declare(strict_types=1);

namespace OCA\Collectives\Controller;

use OCA\Collectives\Events\CollectivesLoadAdditionalScriptsEvent;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

class StartController extends Controller {
	public function __construct(string $AppName,
		IRequest $request,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher) {
		parent::__construct($AppName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		if ($appsMissing = $this->checkDependencies()) {
			return new TemplateResponse('collectives', 'error', ['appsMissing' => $appsMissing]);  // templates/error.php
		}
		$this->eventDispatcher->dispatchTyped(new LoadViewer());
		$this->eventDispatcher->dispatchTyped(new CollectivesLoadAdditionalScriptsEvent());
		return new TemplateResponse('collectives', 'main', [ // templates/main.php
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function indexPath(string $path): TemplateResponse {
		return $this->index();
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
