/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, PlaywrightWorkerArgs } from '@playwright/test'

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { expect, mergeTests } from '@playwright/test'
import { test as collectiveShareTest } from '../support/fixtures/collective-share.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'
import { getToast } from '../support/helpers/toasts.ts'
import { ocsHeaders } from '../support/helpers/urls.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest, collectiveShareTest)

/**
 * Set core app config through the web server, which clears its app config cache
 *
 * `occ` runs in a separate process and can't clear the web server's cache.
 *
 * @param playwright - Playwright worker fixture
 * @param baseURL - Base URL of the Nextcloud instance
 * @param values - Config keys and values to set
 */
async function setCoreConfig(playwright: PlaywrightWorkerArgs['playwright'], baseURL: string | undefined, values: Record<string, string>) {
	const request = await playwright.request.newContext({
		baseURL,
		ignoreHTTPSErrors: true,
		httpCredentials: { username: 'admin', password: 'admin', send: 'always' },
	})
	try {
		for (const [key, value] of Object.entries(values)) {
			await request.post(`/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/core/${key}`, {
				headers: ocsHeaders,
				data: { value },
				failOnStatusCode: true,
			})
		}
	} finally {
		await request.dispose()
	}
}

test.describe('Page share', () => {
	test('Create share and open share unauthenticated', async ({ user, page, collective, pageSidebar, sharePage, shareTitleBar, sharePageList, shareEditor }) => {
		const collectivePage = await collective.createPage({ title: 'Sharepage', content: 'Some content', user, page })
		await collective.createPage({ title: 'Anotherone', user, page })
		await collectivePage.open()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await expect(getToast(page, 'success', `Page "${collectivePage.data.title}" has been shared`)).toBeVisible()

		// Open share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent()).toHaveText('Some content')
		await expect(shareTitleBar.title).toHaveValue('Sharepage')

		// Single page shares show the collective in the header and no navigation
		await expect(sharePage.locator('#header .header-title')).toContainText(collective.data.name)
		await expect(sharePageList.pageListItems.filter({ visible: true })).toHaveCount(0)
		await expect(sharePageList.el).toBeHidden()
	})

	test('Show navigation in share of page with subpages', async ({ user, page, collective, sharePage, sharePageList, shareEditor }) => {
		const collectivePage = await collective.createPage({ title: 'Sharepage', content: 'Some content', user, page })
		await collective.createPage({ title: 'Subpage', parentId: collectivePage.data.id, user, page })
		const share = await collectivePage.createShare({ page })

		await sharePage.goto(share.getShareUrl())
		await expect(shareEditor.getContent()).toHaveText('Some content')
		await expect(sharePageList.el).toBeVisible()
		await expect(sharePageList.getPageItem('Subpage')).toBeVisible()
	})

	test('Show navigation in editable share of page without subpages', async ({ user, page, collective, sharePage, sharePageList, shareEditor }) => {
		const collectivePage = await collective.createPage({ title: 'Sharepage', content: 'Some content', user, page })
		const share = await collectivePage.createShare({ page })
		await share.setEditable(true)

		await sharePage.goto(share.getShareUrl())
		await expect(shareEditor.getContent()).toHaveText('Some content')
		await expect(sharePageList.el).toBeVisible()
	})
})

test.describe('Page share enforced password protection', () => {
	test.slow()

	let sharingTab: Locator
	let shareActionsPanel: Locator

	test.beforeAll(async ({ playwright }, testInfo) => {
		// Enable the app first, so the config writes below also clear its cached enabled state
		await runOcc(['app:enable', '--force', 'password_policy'], { verbose: true })
		await setCoreConfig(playwright, testInfo.project.use.baseURL, {
			shareapi_enable_link_password_by_default: 'yes',
			shareapi_enforce_links_password: 'yes',
		})
	})

	test.afterAll(async ({ playwright }, testInfo) => {
		await setCoreConfig(playwright, testInfo.project.use.baseURL, {
			shareapi_enable_link_password_by_default: 'no',
			shareapi_enforce_links_password: 'no',
		})
		await runOcc(['app:disable', 'password_policy'], { verbose: true })
	})

	test.beforeEach(async ({ user, page, collective, pageSidebar }) => {
		const collectivePage = await collective.createPage({ title: 'Sharepage', content: 'Some content', user, page })
		await collectivePage.open()

		sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		shareActionsPanel = page.getByRole('dialog', { name: 'Share actions' })

		// With enforcement active, the settings panel opens immediately
		await expect(shareActionsPanel).toBeVisible()
		// password_policy pre-fills the password field
		await expect(shareActionsPanel.locator('input[autocomplete="new-password"]')).not.toHaveValue('')
	})

	test('Create share and open share unauthenticated', async ({ page, collective, pageSidebar, sharePage, shareEditor }) => {
		await shareActionsPanel.locator('input[autocomplete="new-password"]').fill('fiej2Ahl5pae')
		await shareActionsPanel.getByRole('button', { name: 'Create share' }).click()
		await expect(getToast(page, 'success', 'has been shared')).toBeVisible()

		await pageSidebar.clickShareMenuAction(sharingTab, 'Advanced settings')
		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeDisabled()

		// Open password-protected share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await sharePage.getByRole('textbox', { name: 'Password' }).pressSequentially('fiej2Ahl5pae')
		await sharePage.getByRole('button', { name: 'Submit' }).click()
		await sharePage.waitForURL(/\/p\/[^/]+\/(?!authenticate)/)
		await expect(shareEditor.getContent()).toHaveText('Some content', { timeout: 15_000 })
	})

	test('Fails to create share with weak password', async ({ page }) => {
		await shareActionsPanel.locator('input[autocomplete="new-password"]').fill('password')
		await shareActionsPanel.getByRole('button', { name: 'Create share' }).click()
		await expect(getToast(page, 'error', 'most common')).toBeVisible()
	})
})
