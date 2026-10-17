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
	let publishFeatureSwitch: Locator

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
		publishFeatureSwitch = page.getByLabel('Enable publish feature')
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

	test('Publish feature can be enabled and disabled', async ({ page }) => {
		await publishFeatureSwitch.setChecked(true, { force: true })
		await page.waitForTimeout(100)
		await page.reload()
		publishFeatureSwitch = page.getByLabel('Enable publish feature')
		await expect(publishFeatureSwitch).toBeChecked()

		await publishFeatureSwitch.setChecked(false, { force: true })
		await page.waitForTimeout(100)
		await page.reload()
		publishFeatureSwitch = page.getByLabel('Enable publish feature')
		await expect(publishFeatureSwitch).not.toBeChecked()
	})

	test('Static site generator settings', async ({ page }) => {
		const urlInput = page.getByRole('textbox', { name: 'Static site generator URL' })
		const urlHelperText = page.locator('#staticSiteGeneratorUrl-helper-text')
		const tokenInput = page.getByLabel('Static site generator token', { exact: true })

		await test.step('are only shown with enabled publish feature', async () => {
			await publishFeatureSwitch.setChecked(false, { force: true })
			await expect(urlInput).not.toBeVisible()
			await expect(tokenInput).not.toBeVisible()

			await publishFeatureSwitch.setChecked(true, { force: true })
			await expect(urlInput).toBeVisible()
			await expect(tokenInput).toHaveValue('')
		})

		await test.step('warn about an invalid URL and save a valid one', async () => {
			await urlInput.fill('ssg.example.org')
			await expect(urlHelperText).toBeVisible()

			// Fails fast in the connection test below, as nothing listens on this port
			await urlInput.fill('http://localhost:1')
			await expect(urlHelperText).not.toBeVisible()
			const responsePromise = page.waitForResponse(/static_site_generator_url/)
			await urlInput.blur()
			expect((await responsePromise).ok()).toBe(true)
		})

		await test.step('save the token and only show a placeholder afterwards', async () => {
			await tokenInput.fill('secret-token')
			const responsePromise = page.waitForResponse(/static_site_generator_token/)
			await tokenInput.blur()
			expect((await responsePromise).ok()).toBe(true)

			await page.reload()
			await expect(tokenInput).toHaveValue('********')
			expect(await page.content()).not.toContain('secret-token')

			let tokenRequestSent = false
			page.on('request', (request) => {
				tokenRequestSent ||= request.url().includes('static_site_generator_token')
			})
			await tokenInput.focus()
			await tokenInput.blur()
			await page.waitForTimeout(200)
			expect(tokenRequestSent).toBe(false)
		})

		await test.step('show the connection test result below the button', async () => {
			await page.getByRole('button', { name: 'Test connection' }).click()
			await expect(page.getByRole('status').filter({ hasText: 'The static site generator is not reachable' }))
				.toBeVisible()
		})
	})
})
