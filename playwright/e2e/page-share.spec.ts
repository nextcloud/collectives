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
import { getToast } from '../support/helpers/toasts.ts'
import { ocsHeaders } from '../support/helpers/urls.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest, collectiveShareTest)

test.describe('Page share', () => {
	test('Create share and open share unauthenticated', async ({ user, page, collective, pageSidebar, sharePage, sharePageList, shareEditor }) => {
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
