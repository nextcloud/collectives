/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { User } from '../support/fixtures/User.ts'

type MemberFixture = { page: Page, user: User }

const baseTest = mergeTests(createCollectivesTest, navigationTest)

// Extend fixture to add a logged-in member user for permission tests
const test = baseTest.extend<{ member: MemberFixture }>({
	member: async ({ collective, browser, baseURL }, use) => {
		const account = await collective.addMember()
		const memberPage = await loginAsUser(browser, baseURL, account)
		await use({ page: memberPage, user: new User(account) })
		await memberPage.close()
	},
})

test.beforeAll(async () => {
	await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'true'])
})

test.describe('Collective publish', () => {
	test('admin can open the publish modal and publish the collective', async ({ collective, navigation, page }) => {
		await collective.openCollective()
		const modal = page.getByRole('dialog')

		await test.step('open and close the publish modal', async () => {
			await page.getByRole('button', { name: 'Collective actions' }).click()

			// Publish button is visible for admin
			const publishButton = page.locator('.action-item__popper:visible')
				.getByRole('button', { name: 'Publish website', exact: true })
			await expect(publishButton).toBeVisible()

			// Publish modal opens on publish button click
			await publishButton.click()
			await expect(modal
				.filter({ has: page.getByRole('heading', { name: `Publish website for Collective ${collective.data.name}` }) }))
				.toBeVisible()

			// Modal can be closed
			await modal.getByRole('button', { name: 'Close' }).click()
			await expect(modal).toHaveCount(0)
		})

		await test.step('close the publish modal with Escape', async () => {
			await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
			await expect(modal).toBeVisible()

			// Escape must also work while the initially focused checkbox has the focus
			await expect(modal.getByRole('checkbox').first()).toBeFocused()
			await page.keyboard.press('Escape')
			await expect(modal).toHaveCount(0)
		})

		await test.step('reopen the publish modal and publish', async () => {
			// Intercept the API call to verify it is sent and succeeds
			const requestPromise = page.waitForRequest((req) => req.url().includes('/static-sites') && req.method() === 'POST')

			// Modal can be opened again
			await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
			await expect(modal).toBeVisible()
			await expect(modal).toContainText(collective.data.name)

			// All pages are pre-selected; click publish
			await modal.getByRole('button', { name: 'Publish website' }).click()

			// The request must reach the backend
			const response = await (await requestPromise).response()
			expect(response?.status()).toBe(200)

			// Modal closes after successful submission
			await expect(modal).toHaveCount(0)
		})
	})

	test('regular members cannot see publish button', async ({ collective, member }) => {
		await member.page.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)

		// Open the current collective's actions menu
		await member.page.getByRole('button', { name: 'Collective actions' }).click()

		// Actions menu is visible for regular member, but without the publish button
		const actionsMenu = member.page.locator('.action-item__popper:visible')
		await expect(actionsMenu).toBeVisible()

		const publishButton = actionsMenu.getByRole('button', { name: 'Publish website', exact: true })
		await expect(publishButton).toHaveCount(0)
	})
})
