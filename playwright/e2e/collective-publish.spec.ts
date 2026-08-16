/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { login } from '@nextcloud/e2e-test-server/playwright'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const baseTest = mergeTests(createCollectiveTest, navigationTest)

// Extend fixture to add a member user for permission tests
const test = baseTest.extend<{ memberAccount: Account }>({
	memberAccount: async ({ collective }, use) => {
		const member = await collective.addMember()
		await use(member)
	},
})

test.describe('Collective publish', () => {
	test('admin can open publish modal', async ({ collective, navigation, page }) => {
		await collective.openCollective()
		await navigation.open()
		const collectiveItem = navigation.getCollectiveItem(collective.data.name)
		await collectiveItem.getByRole('button', { name: 'Actions' }).click()

		// Publish button is visible for admin
		const publishButton = page.getByRole('button', { name: 'Publish', exact: true })
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

	test('regular members cannot see publish button', async ({ collective, memberAccount, browser }) => {
		// Use isolated browser session for member user to avoid permission issues with admin session
		const memberContext = await browser.newContext()
		const memberPage = await memberContext.newPage()

		// Login as member in the new context
		await login(memberPage.request, memberAccount)
		await memberPage.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)

		// Open navigation and actions menu
		await memberPage.getByRole('button', { name: 'Open navigation' }).click()
		const collectiveItem = memberPage.locator('#app-navigation-vue')
			.getByRole('listitem')
			.filter({ has: memberPage.getByRole('link', { name: collective.data.name }) })
		await collectiveItem.getByRole('button', { name: 'Actions' }).click()

		// Publish button should NOT be visible for regular member
		const publishButton = memberPage.getByRole('button', { name: 'Publish', exact: true })
		await expect(publishButton).toHaveCount(0)

		// Cleanup member context
		await memberContext.close()
	})

	test('admin cannot see publish button when feature is disabled', async ({ collective, navigation, page }) => {
		await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'false'])

		try {
			await collective.openCollective()
			await navigation.open()
			const collectiveItem = navigation.getCollectiveItem(collective.data.name)
			await collectiveItem.getByRole('button', { name: 'Actions' }).click()

			const publishButton = page.getByRole('button', { name: 'Publish', exact: true })
			await expect(publishButton).toHaveCount(0)
		} finally {
			await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'true'])
		}
	})
})
