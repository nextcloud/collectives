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
	test('admin can open publish modal', async ({ collective, navigation, page }) => {
		await collective.openCollective()
		await page.getByRole('button', { name: 'Collective actions' }).click()

		// Publish button is visible for admin
		const publishButton = page.locator('.action-item__popper:visible')
			.getByRole('button', { name: 'Publish', exact: true })
		await expect(publishButton).toBeVisible()

		// Publish modal opens on publish button click
		await publishButton.click()
		const modal = page.getByRole('dialog')
		await expect(modal
			.filter({ has: page.getByRole('heading', { name: `Publish website for Collective ${collective.data.name}` }) }))
			.toBeVisible()

		// Modal can be closed
		await modal.getByRole('button', { name: 'Close' }).click()
		await expect(modal).toHaveCount(0)

		// Modal can be opened again
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish')
		const reopenedModal = page.getByRole('dialog')
		await expect(reopenedModal).toBeVisible()
		await expect(reopenedModal).toContainText(collective.data.name)
	})

	test('regular members cannot see publish button', async ({ collective, member }) => {
		await member.page.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)

		// Open the current collective's actions menu
		await member.page.getByRole('button', { name: 'Collective actions' }).click()

		// Actions menu is visible for regular member, but without the publish button
		const actionsMenu = member.page.locator('.action-item__popper:visible')
		await expect(actionsMenu).toBeVisible()

		const publishButton = actionsMenu.getByRole('button', { name: 'Publish', exact: true })
		await expect(publishButton).toHaveCount(0)
	})
})
