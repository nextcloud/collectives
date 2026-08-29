/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator } from '@playwright/test'

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { expect, mergeTests } from '@playwright/test'
import { test as collectiveShareTest } from '../support/fixtures/collective-share.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'
import { ocsHeaders } from '../support/helpers/urls.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest, collectiveShareTest)

test.describe('Page share', () => {
	test('Create share and open share unauthenticated', async ({ user, page, collective, pageSidebar, sharePage, sharePageList, shareEditor }) => {
		const collectivePage = await collective.createPage({ title: 'Sharepage', content: 'Some content', user, page })
		await collective.createPage({ title: 'Anotherone', user, page })
		await collectivePage.open()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await expect(page.locator('.toast-success')).toContainText(`Page "${collectivePage.data.title}" has been shared`)

		// Open share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent()).toHaveText('Some content')

		// The shared page has no subpages, so the page list stays empty
		// ("Anotherone" is not a subpage of it and must not be listed)
		await expect(sharePageList.el).toBeVisible()
		await expect(sharePageList.pageListItems.filter({ visible: true })).toHaveCount(0)
	})
})

test.describe('Page share enforced password protection', () => {
	test.slow()

	let sharingTab: Locator
	let shareActionsPanel: Locator

	test.beforeAll(async () => {
		await runOcc(['config:app:set', 'core', 'shareapi_enable_link_password_by_default', '--value', 'yes'])
		await runOcc(['config:app:set', 'core', 'shareapi_enforce_links_password', '--value', 'yes'])
	})

	test.afterAll(async () => {
		await runOcc(['config:app:set', 'core', 'shareapi_enable_link_password_by_default', '--value', 'no'])
		await runOcc(['config:app:set', 'core', 'shareapi_enforce_links_password', '--value', 'no'])
	})

	test.beforeEach(async ({ user, page, collective, pageSidebar }) => {
		await runOcc(['app:enable', '--force', 'password_policy'])

		// Dirty hack to wait for config changes to propagate through cache
		await expect.poll(async () => {
			const resp = await page.request.get('/ocs/v2.php/cloud/capabilities?format=json', { headers: ocsHeaders, failOnStatusCode: true })
			const caps = await resp.json()
			return caps.ocs.data.capabilities.files_sharing?.public?.password?.enforced
		}, { timeout: 15_000, intervals: [500] }).toBe(true)

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

	test.afterEach(async () => {
		await runOcc(['app:disable', 'password_policy'])
	})

	test('Create share and open share unauthenticated', async ({ page, collective, pageSidebar, sharePage, shareEditor }) => {
		await shareActionsPanel.locator('input[autocomplete="new-password"]').fill('fiej2Ahl5pae')
		await shareActionsPanel.getByRole('button', { name: 'Create share' }).click()
		await expect(page.locator('.toast-success')).toContainText('has been shared')

		await pageSidebar.clickShareMenuAction(sharingTab, 'Advanced settings')
		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeDisabled()

		// Open password-protected share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await sharePage.getByRole('textbox', { name: 'Password' }).pressSequentially('fiej2Ahl5pae')
		await sharePage.getByRole('button', { name: 'Submit' }).click()
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent()).toHaveText('Some content')
	})

	test('Fails to create share with weak password', async ({ page }) => {
		await shareActionsPanel.locator('input[autocomplete="new-password"]').fill('password')
		await shareActionsPanel.getByRole('button', { name: 'Create share' }).click()
		await expect(page.locator('.toast-error')).toContainText('most common')
	})
})
