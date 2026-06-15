<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use OCA\Collectives\Service\CollectiveExportService;
use OCA\Collectives\Service\MissingDependencyException;
use OCA\Collectives\Service\NotFoundException;
use OCA\Collectives\Service\NotPermittedException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\StreamResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class CollectiveExportController extends Controller {
	use UserTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private CollectiveExportService $exportService,
		private LoggerInterface $logger,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Render a page and its subpages to a static HTML site (via Hugo) and download it as a zip archive
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function download(int $collectiveId, int $pageId): StreamResponse|JSONResponse {
		try {
			[$zipPath, $filename] = $this->exportService->createStaticSiteZip($collectiveId, $pageId, $this->getUid());
		} catch (NotFoundException $e) {
			$this->logger->debug('Collective export not found: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (MissingDependencyException $e) {
			$this->logger->error('Collective export dependency missing: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		} catch (NotPermittedException $e) {
			$this->logger->debug('Collective export not permitted: ' . $e->getMessage(), ['exception' => $e]);
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		}

		register_shutdown_function(static function () use ($zipPath): void {
			if (is_file($zipPath)) {
				unlink($zipPath);
			}
		});

		return new StreamResponse(
			$zipPath,
			Http::STATUS_OK,
			[
				'Content-Type' => 'application/zip',
				'Content-Disposition' => 'attachment; filename="' . $filename . '"',
			],
		);
	}
}
