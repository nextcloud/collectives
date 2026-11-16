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

// TODO(Publish): Issue-104: Edits made while the static site was being built aren't detected, as `publishedAt` is the time the publication finished.
/**
 * Whether selected pages of a static site were edited or deleted after its last publication
 *
 * Edits made while the static site was being built aren't detected, as `publishedAt` is the
 * time the publication finished.
 *
 * @param {object} staticSite static site as returned by the backend
 * @param {object[]} pages all pages of the collective, unknown changes if empty
 * @return {boolean} true if the published static site is outdated
 */
export function hasUnpublishedChanges(staticSite, pages) {
	if (!staticSite.publishedAt || !pages.length) {
		return false
	}
	const pagesById = new Map(pages.map((page) => [page.id, page]))
	return staticSite.selectedPageIds.some((pageId) => {
		const page = pagesById.get(pageId)
		return !page || page.timestamp > staticSite.publishedAt
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
