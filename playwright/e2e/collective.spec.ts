/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'

const test = createCollectiveTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: 'many !@#$%^&()_ special chars 🚀' },
	]),
})

test.describe('Collective', () => {
	test.beforeEach(async ({ collective }) => {
		await collective.openCollective()
	})

	test('Can handle special chars in collective name', async ({ collective }) => {
		await expect(collective.page).toHaveTitle(`${collective.data.name} - Collectives - Nextcloud`)
		await expect(collective.page.locator('[data-cy-collectives="page-title-container"] input'))
			.toHaveValue(collective.data.name)
	})
})
