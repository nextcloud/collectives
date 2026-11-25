<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Collectives\Controller;

use Closure;
use OCA\Collectives\Db\StaticSite;
use OCA\Collectives\ResponseDefinitions;
use OCA\Collectives\Service\ServiceException;
use OCA\Collectives\Service\StaticSiteGeneratorException;
use OCA\Collectives\Service\StaticSiteGeneratorNotConfiguredException;
use OCA\Collectives\Service\StaticSiteGeneratorUnavailableException;
use OCA\Collectives\Service\StaticSitePublicationInProgressException;
use OCA\Collectives\Service\StaticSiteService;
use OCA\Collectives\Service\StaticSiteSlugTakenException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Provides access to static site exports of a collective.
 *
 * @psalm-import-type CollectivesStaticSite from ResponseDefinitions
 */
class StaticSiteController extends OCSController {
	use OCSExceptionHelper;
	use UserTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private StaticSiteService $staticSiteService,
		private LoggerInterface $logger,
		private IL10N $l10n,
		private ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Get static sites of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 *
	 * @return DataResponse<Http::STATUS_OK, list<CollectivesStaticSite>, array{}>
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective not found
	 *
	 * 200: Static sites returned
	 */
	#[NoAdminRequired]
	public function index(int $collectiveId): DataResponse {
		$uid = $this->getUid();
		$staticSites = $this->handleErrorResponse(
			fn (): array => $this->staticSiteService->getStaticSites($collectiveId, $uid),
			$this->logger,
		);
		return new DataResponse($staticSites);
	}

	/**
	 * Start a static site export for a selection of pages of a collective
	 *
	 * @param int $collectiveId ID of the collective
	 * @param list<int> $pageIds IDs of the pages to publish
	 * @param ?string $title Title of the static site, defaults to the collective name
	 * @param ?string $slug Slug of the static site (lowercase ASCII letters and numbers, separated by single hyphens), defaults to the slugified collective name
	 * @param ?string $theme Theme of the website ("wiki" page tree sidebar or "topnav" top navigation bar), defaults to "wiki"
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesStaticSite, array{}>|DataResponse<Http::STATUS_CONFLICT, array{message: string}, array{}>
	 * @throws OCSBadRequestException No pages selected, invalid title, slug or theme
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or page not found
	 * @throws OCSException Archive of the static site couldn't be provided, a website of the collective is being published or the publish service is not available or didn't accept it
	 *
	 * 200: Static site created
	 * 409: Slug is already taken by another static site, also of another collective
	 */
	#[NoAdminRequired]
	public function create(int $collectiveId, array $pageIds, ?string $title = null, ?string $slug = null, ?string $theme = null): DataResponse {
		$uid = $this->getUid();
		try {
			$staticSite = $this->handleArchiveErrorResponse(
				fn (): StaticSite => $this->staticSiteService->create($collectiveId, $pageIds, $uid, $title, $slug, $theme),
				['collectiveId' => $collectiveId],
			);
		} catch (StaticSiteSlugTakenException) {
			return $this->slugTakenResponse();
		}
		return new DataResponse($staticSite);
	}

	/**
	 * Update title, page selection, slug and theme of a static site and publish it again
	 *
	 * Every call starts a new publication, even if nothing changed. The slug can only be changed until the static site got published.
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the static site
	 * @param list<int> $pageIds IDs of the pages to publish
	 * @param string $title Title of the static site
	 * @param ?string $slug Slug of the static site (lowercase ASCII letters and numbers, separated by single hyphens), defaults to the current slug
	 * @param ?string $theme Theme of the website ("wiki" page tree sidebar or "topnav" top navigation bar), defaults to the current theme
	 *
	 * @return DataResponse<Http::STATUS_OK, CollectivesStaticSite, array{}>|DataResponse<Http::STATUS_CONFLICT, array{message: string}, array{}>
	 * @throws OCSBadRequestException No pages selected, invalid title, slug or theme, slug of a published static site changed or publication started concurrently
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective, static site or page not found
	 * @throws OCSException Archive of the static site couldn't be provided, a website of the collective is being published or the publish service is not available or didn't accept it
	 *
	 * 200: Static site updated
	 * 409: Slug is already taken by another static site, also of another collective
	 */
	#[NoAdminRequired]
	public function update(int $collectiveId, int $id, array $pageIds, string $title, ?string $slug = null, ?string $theme = null): DataResponse {
		$uid = $this->getUid();
		try {
			$staticSite = $this->handleArchiveErrorResponse(
				fn (): StaticSite => $this->staticSiteService->update($collectiveId, $id, $pageIds, $title, $uid, $slug, $theme),
				['collectiveId' => $collectiveId, 'id' => $id],
			);
		} catch (StaticSiteSlugTakenException) {
			return $this->slugTakenResponse();
		}
		return new DataResponse($staticSite);
	}

	/**
	 * Delete a static site
	 *
	 * The static site is deleted at the publish service first. It keeps status deleting until the
	 * publish service reports it as deleted. If the publish service doesn't know it, it is deleted right away.
	 *
	 * @param int $collectiveId ID of the collective
	 * @param int $id ID of the static site
	 *
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>|DataResponse<Http::STATUS_ACCEPTED, CollectivesStaticSite, array{}>
	 * @throws OCSBadRequestException Static site was changed meanwhile
	 * @throws OCSForbiddenException Not permitted
	 * @throws OCSNotFoundException Collective or static site not found
	 * @throws OCSException A website of the collective is being published or deleted, or the publish service is not available or didn't accept the request
	 *
	 * 200: Static site deleted
	 * 202: Static site is being deleted
	 */
	#[NoAdminRequired]
	public function delete(int $collectiveId, int $id): DataResponse {
		$uid = $this->getUid();
		$staticSite = $this->handleArchiveErrorResponse(
			fn (): ?StaticSite => $this->staticSiteService->delete($collectiveId, $id, $uid),
			['collectiveId' => $collectiveId, 'id' => $id],
		);
		return $staticSite === null
			? new DataResponse([])
			: new DataResponse($staticSite, Http::STATUS_ACCEPTED);
	}

	/**
	 * Returns a translated error for publish failures to tell the user whether trying again later may help. (e.g. publish service is not available)
	 *
	 * Requests to the static site generator are logged by its client, other failures are logged once with context.
	 *
	 * @throws OCSBadRequestException
	 * @throws OCSForbiddenException
	 * @throws OCSNotFoundException
	 * @throws OCSException
	 * @throws StaticSiteSlugTakenException Handled by the caller with a slug taken response
	 *
	 * @template T
	 * @param Closure(): T $callback
	 * @return T
	 */
	private function handleArchiveErrorResponse(Closure $callback, array $context): mixed {
		try {
			return $this->handleErrorResponse($callback, $this->logger);
		} catch (StaticSiteSlugTakenException $e) {
			throw $e;
		} catch (StaticSitePublicationInProgressException) {
			throw new OCSException(
				$this->l10n->t('A website of this collective is being published or deleted. Please wait until it is finished.'),
				Http::STATUS_LOCKED,
			);
		} catch (StaticSiteGeneratorNotConfiguredException) {
			$this->logger->warning('Static site generator is not configured', $context);
			throw new OCSException(
				$this->l10n->t('Publishing websites is not set up. Please contact your administrator.'),
				Http::STATUS_SERVICE_UNAVAILABLE,
			);
		} catch (StaticSiteGeneratorUnavailableException) {
			throw new OCSException(
				$this->l10n->t('The publish service is currently not available. Please try again later.'),
				Http::STATUS_SERVICE_UNAVAILABLE,
			);
		} catch (StaticSiteGeneratorException) {
			throw new OCSException(
				$this->l10n->t('The publish service could not process the request. Please contact your administrator.'),
				Http::STATUS_BAD_GATEWAY,
			);
		} catch (ServiceException $e) {
			$this->logger->error('Failed to provide static site archive', $context + ['exception' => $e]);
			throw new OCSException(
				$this->l10n->t('The website could not be prepared. Please contact your administrator.'),
				Http::STATUS_INTERNAL_SERVER_ERROR,
			);
		}
	}

	/**
	 * @return DataResponse<Http::STATUS_CONFLICT, array{message: string}, array{}>
	 */
	private function slugTakenResponse(): DataResponse {
		return new DataResponse(
			['message' => $this->l10n->t('This web address is already taken by another website')],
			Http::STATUS_CONFLICT,
		);
	}
}
