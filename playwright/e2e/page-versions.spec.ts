/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'
import { getToast } from '../support/helpers/toasts.ts'
import { webdavUrl } from '../support/helpers/urls.ts'

// A page with the versions v1 (initial), v2 and v3 (current)
const test = mergeTests(createCollectivesTest, pageSidebarTest).extend<{ versionedPage: CollectivePage }>({
	versionedPage: async ({ collective, user, page }, use) => {
		const collectivePage = await collective.createPage({ title: 'Versioned page', content: 'v1', user, page })
		for (const content of ['v2', 'v3']) {
			// Changes within the same second don't create a new version
			await page.waitForTimeout(1100)
			await collectivePage.setContent({ content, user, page })
		}
		await use(collectivePage)
	},
})

test.describe('Page versions', () => {
	test.beforeEach(async ({ versionedPage, pageSidebar }) => {
		await versionedPage.open()
		await pageSidebar.openSidebarTab('Versions')
	})

	/**
	 * Click an action of the version at the index, the current version being first
	 *
	 * @param page - The Playwright page
	 * @param index - Index in the versions list
	 * @param action - Name of the action
	 */
	async function clickVersionAction(page: Page, index: number, action: string) {
		await page.locator('.version-list .list-item')
			.nth(index)
			.getByRole('button', { name: /^Actions for versions/ })
			.click()
		await page.getByRole('menuitem', { name: action }).click()
	}

	test('lists and opens versions', async ({ page, pageSidebar }) => {
		const versions = page.locator('.version-list .list-item')
		await expect(versions).toHaveCount(3)
		await expect(versions.first()).toContainText('Current version')
		await expect(versions.last()).toContainText('Initial version')

		await versions.nth(1).click()
		await expect(page.locator('.page-title-container .title-version')).toBeVisible()
		await expect(page.locator('[data-cy-collectives="reader"]')).toContainText('v2')

		await pageSidebar.getVersionListItem('Current version').click()
		await expect(page.locator('.page-title-container .title-version')).toHaveCount(0)
		await expect(page.locator('[data-cy-collectives="reader"]')).toContainText('v3')
	})

	test('names a version', async ({ page }) => {
		await clickVersionAction(page, 1, 'Name this version')
		await page.locator('.version-label-modal').getByRole('textbox').fill('Reviewed')
		await page.keyboard.press('Enter')

		await expect(page.locator('.version-list .list-item').nth(1)).toContainText('Reviewed')
	})

	test('compares a version with the current version', async ({ page }) => {
		await clickVersionAction(page, 2, 'Compare with current version')

		// Second locator can be removed once we only support Nextcloud 36+
		const viewer = page.locator('.viewer__modal, #viewer[role="dialog"]')
		await expect(viewer).toContainText('v1')
		await expect(viewer).toContainText('v3')
	})

	test('compares two selected versions', async ({ page }) => {
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		const dialog = page.getByRole('dialog', { name: 'Compare versions' })
		// Preselected are the previous (v2) and the current version, pick v1 instead of the latter
		await dialog.getByRole('combobox', { name: 'Later version' }).click()
		// The options are appended to the body
		await page.getByRole('option').nth(2).click()
		await dialog.getByRole('button', { name: 'Compare' }).click()

		await expect(dialog).toHaveCount(0)
		// Second locator can be removed once we only support Nextcloud 36+
		const viewer = page.locator('.viewer__modal, #viewer[role="dialog"]')
		await expect(viewer).toContainText('v1')
		await expect(viewer).toContainText('v2')
		await expect(viewer).not.toContainText('v3')
	})

	test('compares again while a comparison is open', async ({ page }) => {
		await clickVersionAction(page, 1, 'Compare with current version')
		// Second locator can be removed once we only support Nextcloud 36+
		const viewer = page.locator('.viewer__modal, #viewer[role="dialog"]')
		await expect(viewer).toContainText('v2')

		// The page sidebar stays open next to the viewer
		await clickVersionAction(page, 2, 'Compare with current version')
		await expect(viewer).toContainText('v1')
		await expect(viewer).toContainText('v3')
		await expect(viewer).not.toContainText('v2')
	})

	test('restores a version', async ({ versionedPage, user, page }) => {
		await clickVersionAction(page, 2, 'Restore version')
		await expect(getToast(page, 'success', 'Restored')).toBeVisible()

		// Check the file instead of reloading: reloading makes the Text client
		// replay its cached document state, overwriting the restored content.
		const { data } = versionedPage
		const response = await page.request.get(webdavUrl(user.account.userId, data.collectivePath, data.filePath, data.fileName))
		expect(await response.text()).toContain('v1')
	})

	test('deletes a version', async ({ page }) => {
		await clickVersionAction(page, 1, 'Delete version')

		await expect(page.locator('.version-list .list-item')).toHaveCount(2)
	})
})
