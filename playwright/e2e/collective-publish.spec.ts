/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const test = mergeTests(createCollectiveTest, navigationTest)

test.describe('Collective publish', () => {
	test.beforeEach(async ({ collective }) => {
		await collective.openCollective()
	})

	test('opens publish modal on publish button click', async ({ collective, navigation, page }) => {
		await navigation.open()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish')

		const modal = page.locator('.collective-publish-modal')
		await expect(modal).toContainText(collective.data.name)

		await modal.getByRole('button', { name: 'Close' }).click()
		await expect(modal).toHaveCount(0)

		await navigation.clickCollectiveMenu(collective.data.name, 'Publish')
		await expect(modal).toContainText(collective.data.name)
	})
})
