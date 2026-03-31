/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User } from '@nextcloud/e2e-test-server'
import type { Locator } from '@playwright/test'

import { login } from '@nextcloud/e2e-test-server/playwright'
import { expect, test } from '@playwright/test'

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
		await page.goto('/index.php/settings/admin/additional')
		defaultUserFolderInput = page.getByRole('textbox', { name: 'Default user folder' })
		defaultUserFolderHelperText = page.locator('#defaultUserFolder-helper-text')
		await expect(defaultUserFolderInput).toBeVisible()
		await expect(defaultUserFolderHelperText).not.toBeVisible()
	})

	test('Default user folder warns about invalid values', async () => {
		expect(await defaultUserFolderInput.inputValue()).toEqual('')
		await defaultUserFolderInput.fill('invalid')
		await expect(defaultUserFolderHelperText).toBeVisible()

		await defaultUserFolderInput.fill('/')
		await expect(defaultUserFolderHelperText).toBeVisible()

		await defaultUserFolderInput.fill('/inv%alid')
		await expect(defaultUserFolderHelperText).toBeVisible()
	})

	test('Default user folder allows valid path', async ({ page }) => {
		await defaultUserFolderInput.fill('/abc')
		await expect(defaultUserFolderHelperText).not.toBeVisible()
		const requestPromise = page.waitForRequest(/default_user_folder/)
		await defaultUserFolderInput.blur()
		await requestPromise
	})

	test('Default user folder allows empty string', async ({ page }) => {
		await defaultUserFolderInput.fill('')
		await expect(defaultUserFolderHelperText).not.toBeVisible()
		const requestPromise = page.waitForRequest(/default_user_folder/)
		await defaultUserFolderInput.blur()
		await requestPromise
	})
})
