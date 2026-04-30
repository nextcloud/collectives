/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest)

test.describe('Collective share', () => {
	test('Allows to share a collective', async ({ page, collective, pageSidebar }) => {
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await expect(page.locator('.toast-success')).toContainText(`Collective "${collective.data.name}" has been shared`)

		await sharingTab.getByRole('button', { name: 'Copy public link' }).click()
		// await expect(page.locator('.toast-success').filter({ hasText: 'Link copied' })).toBeVisible()
	})
})

test.describe('Collective share password protection', () => {
	test('Allows to set a password via advanced settings', async ({ collective, pageSidebar }) => {
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await pageSidebar.openShareAdvancedSettings(sharingTab)

		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).not.toBeChecked()

		await settingsPanel.locator('.checkbox-radio-switch__content').click()
		await settingsPanel.getByRole('button', { name: 'Update Share' }).click()
		await expect(settingsPanel).not.toBeVisible()

		await pageSidebar.openShareAdvancedSettings(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()
	})

	test('Allows to remove password via advanced settings', async ({ page, collective, pageSidebar }) => {
		const share = await collective.createShare({ password: 'test-password', page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.openShareAdvancedSettings(sharingTab)

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
