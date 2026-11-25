/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test, vi } from 'vitest'
import { hasUnpublishedChanges, mostRecentlyUpdatedStaticSite, staticSiteFailureReason, staticSiteState, unpublishedChanges } from '../../util/staticSiteStatus.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, message) => message }))

describe('staticSiteState', () => {
	test.each([
		[{ status: 'pending', isInProgress: true }, 'publishing'],
		[{ status: 'provided', isInProgress: true }, 'publishing'],
		[{ status: 'published', isInProgress: false }, 'live'],
		[{ status: 'failed', isInProgress: false }, 'failed'],
		// Aborted publications
		[{ status: 'pending', isInProgress: false }, 'failed'],
		[{ status: 'provided', isInProgress: false }, 'failed'],
		[{ status: 'deleting', isInProgress: true }, 'deleting'],
		[{ status: 'failed', isInProgress: false, lastError: 'generator_delete_failed' }, 'deleteFailed'],
		// Aborted deletion
		[{ status: 'deleting', isInProgress: false }, 'deleteFailed'],
	])('returns the state of %j', (staticSite, state) => {
		expect(staticSiteState(staticSite)).toBe(state)
	})
})

describe('hasUnpublishedChanges', () => {
	const staticSite = {
		selectedPageIds: [1, 2, 3],
		publishedSelectedPageIds: [1, 2],
		publishedBuildStartedAt: 200,
		publishedAt: 250,
	}
	const pages = [
		{ id: 1, timestamp: 100 },
		{ id: 2, timestamp: 200 },
		{ id: 3, timestamp: 300 },
	]

	test('returns false if no published page changed after the build started', () => {
		expect(hasUnpublishedChanges(staticSite, pages)).toBe(false)
	})

	test('returns true if a published page was edited while the build was running', () => {
		expect(hasUnpublishedChanges(staticSite, [{ id: 1, timestamp: 100 }, { id: 2, timestamp: 201 }])).toBe(true)
	})

	test('returns true if a published page was deleted after publishing', () => {
		expect(hasUnpublishedChanges(staticSite, [{ id: 1, timestamp: 100 }, { id: 3, timestamp: 300 }])).toBe(true)
	})

	test('ignores pages that are only selected for the next publication', () => {
		expect(hasUnpublishedChanges(staticSite, [...pages, { id: 3, timestamp: 400 }])).toBe(false)
	})

	test('falls back to the publication time if the build start is unknown', () => {
		const legacyStaticSite = { ...staticSite, publishedBuildStartedAt: null }
		expect(hasUnpublishedChanges(legacyStaticSite, [{ id: 1, timestamp: 100 }, { id: 2, timestamp: 250 }])).toBe(false)
		expect(hasUnpublishedChanges(legacyStaticSite, [{ id: 1, timestamp: 100 }, { id: 2, timestamp: 251 }])).toBe(true)
	})

	test('returns false for a static site that was never published', () => {
		const unpublished = { ...staticSite, publishedSelectedPageIds: null, publishedBuildStartedAt: null, publishedAt: null }
		expect(hasUnpublishedChanges(unpublished, [{ id: 1, timestamp: 300 }])).toBe(false)
	})

	test('returns false without known pages', () => {
		expect(hasUnpublishedChanges(staticSite, [])).toBe(false)
	})
})

describe('unpublishedChanges', () => {
	const staticSite = {
		publishedSelectedPageIds: [1, 2, 3, 4],
		publishedBuildStartedAt: 200,
		publishedAt: 250,
	}

	test('returns edited published pages and counts deleted ones', () => {
		const edited = { id: 2, timestamp: 201 }
		expect(unpublishedChanges(staticSite, [{ id: 1, timestamp: 100 }, edited, { id: 5, timestamp: 300 }]))
			.toEqual({ changedPages: [edited], deletedPageCount: 2 })
	})

	test('returns no changes for a static site that was never published', () => {
		const unpublished = { publishedSelectedPageIds: null, publishedBuildStartedAt: null, publishedAt: null }
		expect(unpublishedChanges(unpublished, [{ id: 1, timestamp: 300 }]))
			.toEqual({ changedPages: [], deletedPageCount: 0 })
	})

	test('returns no changes without known pages', () => {
		expect(unpublishedChanges(staticSite, [])).toEqual({ changedPages: [], deletedPageCount: 0 })
	})
})

describe('staticSiteFailureReason', () => {
	test('translates the error of a failed publication', () => {
		expect(staticSiteFailureReason({ status: 'failed', lastError: 'generator_unavailable' }))
			.toBe('The publish service is currently not available. Please try again later.')
	})

	test('explains an aborted publication', () => {
		expect(staticSiteFailureReason({ status: 'provided', lastError: null }))
			.toBe('The publish service didn\'t respond in time.')
	})

	test('translates the error of a failed deletion', () => {
		expect(staticSiteFailureReason({ status: 'failed', lastError: 'generator_delete_failed' }))
			.toBe('The publish service could not delete the website. Please try again later.')
	})

	test('explains an aborted deletion', () => {
		expect(staticSiteFailureReason({ status: 'deleting', lastError: null }))
			.toBe('The publish service didn\'t respond in time.')
	})
})

describe('mostRecentlyUpdatedStaticSite', () => {
	test('returns the static site with the newest updatedAt', () => {
		const newest = { id: 2, updatedAt: 300 }
		expect(mostRecentlyUpdatedStaticSite([{ id: 1, updatedAt: 100 }, newest, { id: 3, updatedAt: 200 }])).toBe(newest)
	})

	test('returns null without static sites', () => {
		expect(mostRecentlyUpdatedStaticSite([])).toBeNull()
	})
})
