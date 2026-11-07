/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, test, vi } from 'vitest'
import { staticSiteErrorMessage } from '../../util/staticSiteError.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, message) => message }))

describe('staticSiteErrorMessage', () => {
	test.each([
		['generator_unavailable', 'The publish service is currently not available. Please try again later.'],
		['generator_rejected', 'The publish service could not process the request. Please contact your administrator.'],
		['generator_failed', 'The publish service could not build the website. Please contact your administrator.'],
	])('translates %j', (lastError, message) => {
		expect(staticSiteErrorMessage(lastError)).toBe(message)
	})

	test.each([
		null,
		'unknown_error',
	])('falls back to a generic message for %j', (lastError) => {
		expect(staticSiteErrorMessage(lastError)).toBe('Publishing the website failed.')
	})
})
