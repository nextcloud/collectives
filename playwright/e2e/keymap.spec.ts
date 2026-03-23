/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

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
		await expect(page.locator('.unified-search-modal')).toBeVisible()

		// Third Ctrl-F: close unified search (focus browsers search util, but that's not testable)
		await page.keyboard.press('Control+f')
		await expect(page.locator('.unified-search-modal')).not.toBeVisible()
		await expect(pageFilter).not.toBeFocused()
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

		// Second Ctrl-F: open unified search
		await page.keyboard.press('Control+f')
		await expect(page.locator('.unified-search-modal')).toBeVisible()

		// Third Ctrl-F: close unified search (focus browsers search util, but that's not testable)
		await page.keyboard.press('Control+f')
		await expect(page.locator('.unified-search-modal')).not.toBeVisible()
	})
})
