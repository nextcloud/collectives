/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { staticSiteErrorMessage } from './staticSiteError.js'

/**
 * State of the last publication of a static site as shown to the user
 *
 * A publication that stays in progress beyond the backend timeout counts as failed.
 *
 * @param {object} staticSite static site as returned by the backend
 * @return {'publishing'|'live'|'failed'} state
 */
export function staticSiteState(staticSite) {
	if (staticSite.isInProgress) {
		return 'publishing'
	}
	return staticSite.status === 'published' ? 'live' : 'failed'
}

/**
 * Whether published pages of a static site were edited or deleted after the build that is online started
 *
 * Static sites published before builds were tracked only know when the publication finished,
 * so edits made while they were being built aren't detected.
 *
 * Only the time a page file was modified is compared. Changing an attachment of a page doesn't
 * modify the page file, so it isn't detected.
 *
 * @param {object} staticSite static site as returned by the backend
 * @param {object[]} pages all pages of the collective, unknown changes if empty
 * @return {boolean} true if the published static site is outdated
 */
export function hasUnpublishedChanges(staticSite, pages) {
	const publishedPageIds = staticSite.publishedSelectedPageIds
	const builtAt = staticSite.publishedBuildStartedAt ?? staticSite.publishedAt
	if (!publishedPageIds || !builtAt || !pages.length) {
		return false
	}
	const pagesById = new Map(pages.map((page) => [page.id, page]))
	return publishedPageIds.some((pageId) => {
		const page = pagesById.get(pageId)
		return !page || page.timestamp > builtAt
	})
}

/**
 * Translated reason why the last publication of a static site failed
 *
 * @param {object} staticSite static site in state failed
 * @return {string} translated reason
 */
export function staticSiteFailureReason(staticSite) {
	return staticSite.status === 'failed'
		? staticSiteErrorMessage(staticSite.lastError)
		: t('collectives', 'The publish service didn\'t respond in time.')
}

/**
 * Static site the user updated last
 *
 * @param {object[]} staticSites static sites as returned by the backend
 * @return {?object} static site with the newest `updatedAt`, null without static sites
 */
export function mostRecentlyUpdatedStaticSite(staticSites) {
	return staticSites.reduce((recent, staticSite) => (!recent || staticSite.updatedAt > recent.updatedAt ? staticSite : recent), null)
}
