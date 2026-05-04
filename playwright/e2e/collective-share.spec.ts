/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as collectiveShareTest } from '../support/fixtures/collective-share.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest, collectiveShareTest)

test.describe('Collective share', () => {
	test('Create share and open share unauthenticated', async ({ user, page, collective, pageSidebar, sharePage, shareEditor, sharePageList }) => {
		await collective.createPage({ title: 'Subpage', user, page })
		await collective.createPage({ title: 'Anotherone', user, page })
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await expect(page.locator('.toast-success')).toContainText(`Collective "${collective.data.name}" has been shared`)

		const shares = await collective.getShares(page)

		// Open share
		await sharePage.goto(shares[0].getShareUrl())
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent().getByRole('heading', { name: 'Welcome' })).toBeVisible()

		// Allows to filter pages
		await expect(sharePageList.el).toBeVisible()
		await expect(sharePageList.pageListItems.filter({ visible: true })).toHaveCount(3)
		await sharePageList.filter.fill('page')
		await expect(sharePageList.pageListItems.filter({ visible: true })).toHaveCount(2)
	})

	test('Create read-write share and open share unauthenticated', async ({ page, collective, pageSidebar, sharePage, shareEditor, sharePageList, shareTitleBar }) => {
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await pageSidebar.selectShareDropdownOption(sharingTab, 'Can edit')
		await expect(page.locator('.toast-success').filter({ hasText: /Share link .* has been updated/ })).toBeVisible()

		// Open share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent().getByRole('heading', { name: 'Welcome' })).toBeVisible()

		// Edit existing content
		await shareEditor.switchMode(true)
		await shareEditor.replaceContent('Edited content')
		await shareEditor.save()
		await shareEditor.switchMode(false)
		await expect(shareEditor.getContent()).toHaveText('Edited content')

		// Create a new page
		await sharePageList.addPage(collective.data.name)
		await shareEditor.editor.locator('.ProseMirror').waitFor({ state: 'visible' })
		await shareTitleBar.title.fill('First page')
		await shareTitleBar.title.press('Enter')

		await expect(sharePageList.getPageItem('New page')).toBeVisible()
	})

	test('Unshare collective and verify share is inaccessible', async ({ page, collective, pageSidebar, sharePage }) => {
		const share = await collective.createShare({ page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.clickShareMenuAction(sharingTab, 'Unshare')
		await expect(page.locator('.toast-success')).toContainText(/Collective .* has been unshared/)

		const response = await sharePage.goto(share.getShareUrl())
		expect(response?.status()).toBe(404)
	})
})

test.describe('Collective share password protection', () => {
	test('Create share with password and open share unauthenticated', async ({ page, collective, pageSidebar, sharePage, shareEditor }) => {
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await pageSidebar.clickShareMenuAction(sharingTab, 'Advanced settings')

		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).not.toBeChecked()

		// Enable password protection
		await settingsPanel.locator('.checkbox-radio-switch__content').click()
		await settingsPanel.getByRole('textbox', { name: 'Password' }).fill('test-password')
		await settingsPanel.getByRole('button', { name: 'Update Share' }).click()
		await expect(settingsPanel).not.toBeVisible()

		await pageSidebar.clickShareMenuAction(sharingTab, 'Advanced settings')
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()

		// Open password-protected share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await sharePage.getByRole('textbox', { name: 'Password' }).pressSequentially('test-password')
		await sharePage.getByRole('button', { name: 'Submit' }).click()
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent().getByRole('heading', { name: 'Welcome' })).toBeVisible()
	})

	test('Remove password via advanced settings', async ({ page, collective, pageSidebar }) => {
		const share = await collective.createShare({ password: 'test-password', page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.clickShareMenuAction(sharingTab, 'Advanced settings')

		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()

		await settingsPanel.locator('.checkbox-radio-switch__content').click()
		await settingsPanel.getByRole('button', { name: 'Update Share' }).click()
		await expect(settingsPanel).not.toBeVisible()

		const updatedShare = await share.updateData()
		expect(updatedShare.hasPassword).toBe(false)
	})

	test('Quick-dropdown permission change preserves hasPassword', async ({ page, collective, pageSidebar }) => {
		const share = await collective.createShare({ password: 'test-password', page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.selectShareDropdownOption(sharingTab, 'Can edit')

		await expect(page.locator('.toast-success')).toBeVisible()

		const updatedShare = await share.updateData()
		expect(updatedShare.hasPassword).toBe(true)
	})
})
