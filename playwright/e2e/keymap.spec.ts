/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { hasServerVersion } from '../support/helpers/server.ts'

const test = mergeTests(createCollectivesTest, navigationTest)

test.describe('Keyboard shortcuts', () => {
	test('Ctrl-F in page filter', async ({ page, collective }) => {
		await collective.openCollective()
		const pageFilter = page.getByLabel('Search pages')

		// First Ctrl-F: focus page filter
		await expect(pageFilter).not.toBeFocused()
		await page.keyboard.press('Control+f')
		await expect(pageFilter).toBeFocused()

		// Second Ctrl-F: open unified search
		await page.keyboard.press('Control+f')
		await expect(pageFilter).not.toBeFocused()
		if (hasServerVersion(32, 33, 34)) {
			// Before NC 35: show unified search modal
			await expect(page.locator('.unified-search-modal-root')).toBeVisible()
		} else {
			// Afterwards: Focus search entry in title bar
			await expect(page.locator('.unified-search-input input')).toBeFocused()
		}
	})

	test('Ctrl-F in MemberPicker', async ({ page, collective, navigation }) => {
		await collective.openCollective()

		await navigation.open()
		await navigation.clickCollectiveMenu(collective.data.name, 'Manage members')
		await expect(page.getByRole('dialog')
			.filter({ has: page.getByRole('heading', { name: 'Members of collective' }) }))
			.toBeVisible()

		const memberSearch = page.getByLabel('Search accounts, groups, teams')
		await expect(memberSearch).toBeFocused()
		await page.locator('.member-picker-list').click()

		// First Ctrl-F: focus member search
		await expect(memberSearch).not.toBeFocused()
		await page.keyboard.press('Control+f')
		await expect(memberSearch).toBeFocused()

		// Second Ctrl-F
		await page.keyboard.press('Control+f')
		if (hasServerVersion(32, 33, 34)) {
			// Before NC 35: open unified search
			await expect(page.locator('.unified-search-modal-root')).toBeVisible()
		} else {
			// Afterwards: do not open unified search when modal is open
			await expect(page.locator('.unified-search-input input')).not.toBeFocused()
		}
	})
})
