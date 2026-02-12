/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../support/fixtures/create-collective.ts'

test.describe('Collective', () => {
	const collectiveName = 'many !@#$%^&()_ special chars 🚀'
	test.use({ collectiveName })

	test.beforeEach(async ({ collective }) => {
		await collective.open()
	})

	test('Can handle special chars in collective name', async ({ collective }) => {
		await expect(collective.page).toHaveTitle(`${collectiveName} - Collectives - Nextcloud`)
		await expect(collective.page.locator('[data-cy-collectives="page-title-container"] input'))
			.toHaveValue(collectiveName)
	})
})
