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
	private IAppManager $appManager;
	private IEventDispatcher $eventDispatcher;

	public function __construct(string $AppName,
		IRequest $request,
		IAppManager $appManager,
		IEventDispatcher $eventDispatcher
	) {
		parent::__construct($AppName, $request);
		$this->appManager = $appManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $path
	 *
	 * @return TemplateResponse
	 */
	public function index(string $path): TemplateResponse {
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
