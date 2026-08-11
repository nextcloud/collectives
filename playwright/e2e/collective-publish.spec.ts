/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { login } from '@nextcloud/e2e-test-server/playwright'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const baseTest = mergeTests(createCollectivesTest, navigationTest)

// Extend fixture to add a member user for permission tests
const test = baseTest.extend<{ memberAccount: Account }>({
	memberAccount: async ({ collective }, use) => {
		const member = await collective.addMember()
		await use(member)
	},
})

/**
 * The `publish_enabled` app config change made via `runOcc` is not always visible on the
 * very first page load right after the change - there can be a short propagation delay
 * before the server-rendered initial state reflects it. Reload until it does.
 */
async function waitForPublishEnabledState(page: Page, expected: boolean): Promise<void> {
	await expect.poll(async () => {
		await page.reload()
		const raw = await page.locator('#initial-state-collectives-publish_enabled').getAttribute('value')
		return raw && JSON.parse(Buffer.from(raw, 'base64').toString('utf-8'))
	}, { timeout: 15_000 }).toBe(expected)
}

test.describe('Collective publish', () => {
	test('admin can open publish modal', async ({ collective, navigation, page }) => {
		await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'true'])
		await collective.openCollective()
		await waitForPublishEnabledState(page, true)
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

	test('regular members cannot see publish button', async ({ collective, memberAccount, browser }) => {
		await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'true'])

		// Use isolated browser session for member user to avoid permission issues with admin session
		const memberContext = await browser.newContext()
		const memberPage = await memberContext.newPage()

		// Login as member in the new context
		await login(memberPage.request, memberAccount)
		await memberPage.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)
		await waitForPublishEnabledState(memberPage, true)

		// Open the current collective's actions menu
		await memberPage.getByRole('button', { name: 'Collective actions' }).click()

		// Publish button should NOT be visible for regular member
		const publishButton = memberPage.locator('.action-item__popper:visible')
			.getByRole('button', { name: 'Publish', exact: true })
		await expect(publishButton).toHaveCount(0)

		// Cleanup member context
		await memberContext.close()
	})

	test('publish feature toggle can be switched via app config', async ({ collective, page }) => {
		await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'true'])
		await collective.openCollective()
		await waitForPublishEnabledState(page, true)
		await page.getByRole('button', { name: 'Collective actions' }).click()
		await expect(page.locator('.action-item__popper:visible')
			.getByRole('button', { name: 'Publish', exact: true })).toBeVisible()

		await runOcc(['config:app:set', 'collectives', 'publish_enabled', '--value', 'false'])
		await waitForPublishEnabledState(page, false)
		await page.getByRole('button', { name: 'Collective actions' }).click()
		await expect(page.locator('.action-item__popper:visible')
			.getByRole('button', { name: 'Publish', exact: true })).toHaveCount(0)
	})
})
