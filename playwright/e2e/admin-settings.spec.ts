/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { APIRequestContext, Locator } from '@playwright/test'

import { login } from '@nextcloud/e2e-test-server/playwright'
import { expect, test } from '@playwright/test'

const defaultUserFolderUrl = '/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/collectives/default_user_folder'

/**
 * Set the default user folder app config, or delete it if value is empty
 *
 * @param request the request context of an admin
 * @param value the new value
 */
async function setDefaultUserFolder(request: APIRequestContext, value: string) {
	const headers = { 'OCS-APIRequest': 'true' }
	if (value) {
		await request.post(defaultUserFolderUrl, { headers, form: { value }, failOnStatusCode: true })
	} else {
		await request.delete(defaultUserFolderUrl, { headers, failOnStatusCode: true })
	}
}

test.describe('Admin settings', () => {
	let defaultUserFolderInput: Locator
	let defaultUserFolderHelperText: Locator

	test.beforeEach(async ({ page }) => {
		const admin: User = {
			userId: 'admin',
			password: 'admin',
			language: 'en-US',
		}
		await login(page.request, admin)
		await setDefaultUserFolder(page.request, '')
		defaultUserFolderInput = page.getByRole('textbox', { name: 'Default user folder' })
		defaultUserFolderHelperText = page.locator('#defaultUserFolder-helper-text')
	})

	test('Default user folder warns about invalid values', async ({ page }) => {
		await page.goto('/index.php/settings/admin/additional')
		await expect(defaultUserFolderInput).toHaveValue('')
		await defaultUserFolderInput.fill('invalid')
		await expect(defaultUserFolderHelperText).toBeVisible()

		await defaultUserFolderInput.fill('/')
		await expect(defaultUserFolderHelperText).toBeVisible()

		await defaultUserFolderInput.fill('/inv%alid')
		await expect(defaultUserFolderHelperText).toBeVisible()
	})

	test('Default user folder allows valid path', async ({ page }) => {
		await page.goto('/index.php/settings/admin/additional')
		await defaultUserFolderInput.fill('/abc')
		await expect(defaultUserFolderHelperText).not.toBeVisible()
		const responsePromise = page.waitForResponse(/default_user_folder/)
		await defaultUserFolderInput.blur()
		expect((await responsePromise).ok()).toBe(true)
	})

	test('Default user folder allows empty string', async ({ page }) => {
		await setDefaultUserFolder(page.request, '/abc')
		await page.goto('/index.php/settings/admin/additional')
		await expect(defaultUserFolderInput).toHaveValue('/abc')
		await defaultUserFolderInput.fill('')
		await expect(defaultUserFolderHelperText).not.toBeVisible()
		const responsePromise = page.waitForResponse(/default_user_folder/)
		await defaultUserFolderInput.blur()
		expect((await responsePromise).ok()).toBe(true)
	})
})
