/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { staticSiteErrorMessage } from './staticSiteError.js'

/**
 * State of the last publication or deletion of a static site as shown to the user
 *
 * A publication or deletion that stays in progress beyond the backend timeout counts as failed.
 *
 * @param {object} staticSite static site as returned by the backend
 * @return {'publishing'|'deleting'|'live'|'failed'|'deleteFailed'} state
 */
export function staticSiteState(staticSite) {
	if (staticSite.status === 'deleting') {
		return staticSite.isInProgress ? 'deleting' : 'deleteFailed'
	}
	if (staticSite.isInProgress) {
		return 'publishing'
	}
	if (staticSite.status === 'published') {
		return 'live'
	}
	return staticSite.lastError === 'generator_delete_failed' ? 'deleteFailed' : 'failed'
}

/**
 * Published pages of a static site that were edited or deleted after the build that is online started
 *
 * Static sites published before builds were tracked only know when the publication finished,
 * so edits made while they were being built aren't detected.
 *
 * Only the time a page file was modified is compared. Changing an attachment of a page doesn't
 * modify the page file, so it isn't detected.
 *
 * Only the IDs of published pages are known, so deleted pages are only counted.
 *
 * @param {object} staticSite static site as returned by the backend
 * @param {object[]} pages all pages of the collective, unknown changes if empty
 * @return {{ changedPages: object[], deletedPageCount: number }} edited pages and number of deleted pages
 */
export function unpublishedChanges(staticSite, pages) {
	const publishedPageIds = staticSite.publishedSelectedPageIds
	const builtAt = staticSite.publishedBuildStartedAt ?? staticSite.publishedAt
	if (!publishedPageIds || !builtAt || !pages.length) {
		return { changedPages: [], deletedPageCount: 0 }
	}
	const pagesById = new Map(pages.map((page) => [page.id, page]))
	const changedPages = []
	let deletedPageCount = 0
	for (const pageId of publishedPageIds) {
		const page = pagesById.get(pageId)
		if (!page) {
			deletedPageCount++
		} else if (page.timestamp > builtAt) {
			changedPages.push(page)
		}
	}
	return { changedPages, deletedPageCount }
}

/**
 * Whether published pages of a static site were edited or deleted after the build that is online started
 *
 * @param {object} staticSite static site as returned by the backend
 * @param {object[]} pages all pages of the collective, unknown changes if empty
 * @return {boolean} true if the published static site is outdated
 */
export function hasUnpublishedChanges(staticSite, pages) {
	const { changedPages, deletedPageCount } = unpublishedChanges(staticSite, pages)
	return changedPages.length > 0 || deletedPageCount > 0
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
