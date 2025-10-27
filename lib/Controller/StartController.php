<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Events\CollectivesLoadAdditionalScriptsEvent;
use OCA\Viewer\Event\LoadViewer;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\AppFramework\Http\StreamResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class StartController extends Controller {
	public function __construct(
		string $AppName,
		IRequest $request,
		private IAppManager $appManager,
		private IEventDispatcher $eventDispatcher,
	) {
		parent::__construct($AppName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		if ($appsMissing = $this->checkDependencies()) {
			return new TemplateResponse('collectives', 'error', ['appsMissing' => $appsMissing]);  // templates/error.php
		}
		$this->eventDispatcher->dispatchTyped(new LoadViewer());
		$this->eventDispatcher->dispatchTyped(new CollectivesLoadAdditionalScriptsEvent());
		$response = new TemplateResponse('collectives', 'main', [ // templates/main.php
			'id-app-content' => '#app-content-vue',
			'id-app-navigation' => '#app-navigation-vue',
		]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain("'self'");
		$policy->addAllowedScriptDomain("'self'");
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function indexPath(string $path): TemplateResponse {
		return $this->index();
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function serviceWorker(): StreamResponse {
		$response = new StreamResponse(__DIR__ . '/../../js/service-worker.js');
		$response->setHeaders([
			'Content-Type' => 'application/javascript',
			'Service-Worker-Allowed' => '/',
		]);

		$policy = new ContentSecurityPolicy();
		$policy->addAllowedWorkerSrcDomain("'self'");
		$policy->addAllowedScriptDomain("'self'");
		$policy->addAllowedConnectDomain("'self'");
		$response->setContentSecurityPolicy($policy);

		return $response;
	}

	private function checkDependencies(): array {
		$apps = ['circles', 'files_versions', 'text', 'viewer'];
		$appsMissing = [];
		foreach ($apps as $app) {
			if (!$this->appManager->isEnabledForUser($app)) {
				$appInfo = $this->appManager->getAppInfo($app);
				$appsMissing[] = $appInfo['name'] ?? $app;
			}
		}
		return $appsMissing;
	}
}
