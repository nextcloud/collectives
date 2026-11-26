/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as collectiveShareTest } from '../support/fixtures/collective-share.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { getToast } from '../support/helpers/toasts.ts'
import { ocsHeaders } from '../support/helpers/urls.ts'
import { PageSidebarSection } from '../support/sections/PageSidebarSection.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest, collectiveShareTest)

test.describe('Collective share', () => {
	test('Create share and open share unauthenticated', async ({ user, page, collective, pageSidebar, sharePage, shareEditor, sharePageList }) => {
		await collective.createPage({ title: 'Subpage', user, page })
		await collective.createPage({ title: 'Anotherone', user, page })
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await expect(getToast(page, 'success', `Collective "${collective.data.name}" has been shared`)).toBeVisible()

		const shares = await collective.getShares(page)

		// Open share
		await sharePage.goto(shares[0].getShareUrl())
		await expect(shareEditor.getContent()).toBeVisible()
		await expect(shareEditor.getContent().getByRole('heading', { name: 'Welcome' })).toBeVisible()

		// Shows the collective in the header instead of the collective selector
		await expect(sharePage.locator('#header .header-title')).toContainText(collective.data.name)
		await expect(sharePage.locator('.collective-selector')).toHaveCount(0)
		// Older Nextcloud versions expose the header action link with role "button"
		await expect(sharePage.locator('#header a', { hasText: 'Export or print' }))
			.toHaveAttribute('href', new RegExp(`/p/${shares[0].data.token}/print/`))

		// Allows to filter pages
		await expect(sharePageList.el).toBeVisible()
		await expect(sharePageList.pageListItems.filter({ visible: true })).toHaveCount(2)
		await sharePageList.filter.fill('page')
		await expect(sharePageList.pageListItems.filter({ visible: true })).toHaveCount(1)
	})

	test('Create read-write share and open share unauthenticated', async ({ page, collective, pageSidebar, sharePage, shareEditor, sharePageList, shareTitleBar }) => {
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await pageSidebar.selectShareDropdownOption(sharingTab, 'Can edit')
		await expect(getToast(page, 'success', /Share link .* has been updated/)).toBeVisible()

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
		await sharePageList.addTopLevelPage()
		await shareEditor.contentLocator(true).waitFor({ state: 'visible' })
		await shareTitleBar.title.fill('First page')
		await shareTitleBar.title.press('Enter')

		await expect(sharePageList.getPageItem('New page')).toBeVisible()
	})

	test('Set and remove share label via advanced settings', async ({ page, collective, pageSidebar }) => {
		const share = await collective.createShare({ page })
		expect(share.data.label).toBe('')
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		const shareTitle = sharingTab.locator('.sharing-entry__title')
		await expect(shareTitle).toHaveText('Share link')

		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')
		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await settingsPanel.getByRole('textbox', { name: 'Share label' }).fill('Newsletter')
		await settingsPanel.getByRole('button', { name: 'Update share' }).click()
		await expect(settingsPanel).not.toBeVisible()
		await expect(shareTitle).toHaveText('Newsletter')
		expect((await share.updateData()).label).toBe('Newsletter')

		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')
		await expect(settingsPanel.getByRole('textbox', { name: 'Share label' })).toHaveValue('Newsletter')
		await settingsPanel.getByRole('textbox', { name: 'Share label' }).clear()
		await settingsPanel.getByRole('button', { name: 'Update share' }).click()
		await expect(settingsPanel).not.toBeVisible()
		await expect(shareTitle).toHaveText('Share link')
		expect((await share.updateData()).label).toBe('')
	})

	test('Generate QR code of share link', async ({ page, collective, pageSidebar }) => {
		await collective.createShare({ page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.clickShareMenuAction(sharingTab, 'Generate QR code')

		const dialog = page.getByRole('dialog', { name: 'Share link' })
		await expect(dialog.getByRole('img', { name: 'QR code of the share link' }))
			.toHaveAttribute('src', /^data:image\/png;base64,/)
		await dialog.getByRole('button', { name: 'Close' }).click()
		await expect(dialog).not.toBeVisible()
	})

	test('Unshare collective and verify share is inaccessible', async ({ page, collective, pageSidebar, sharePage }) => {
		const share = await collective.createShare({ page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.clickShareMenuAction(sharingTab, 'Unshare')
		await expect(getToast(page, 'success', /Collective .* has been unshared/)).toBeVisible()

		const response = await sharePage.goto(share.getShareUrl())
		expect(response?.status()).toBe(404)
	})
})

test.describe('Share links of other members', () => {
	test('Admin sees and deletes share link of another member', async ({ page, collective, pageSidebar, browser, baseURL }) => {
		const account = await collective.addMember()
		// A display name that differs from the user ID and contains characters that need escaping
		const displayName = 'Alice O\'Brien & <Co>'
		const memberPage = await loginAsUser(browser, baseURL, account)
		try {
			await memberPage.request.put(`/ocs/v2.php/cloud/users/${account.userId}`, {
				headers: ocsHeaders,
				data: { key: 'displayname', value: displayName },
				failOnStatusCode: true,
			})
			await collective.createShare({ page: memberPage })
		} finally {
			await memberPage.close()
		}

		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		// Admin can still create an own share link
		await expect(sharingTab.getByRole('button', { name: 'Create a new share link' })).toBeVisible()

		const title = `Share link (by ${displayName})`
		const entry = pageSidebar.getShareEntry(sharingTab, title)
		await expect(entry).toBeVisible()
		await expect(entry.locator('.sharing-entry__title')).toHaveText(title)
		await expect(entry.locator('.share-permission')).toHaveText('View only')
		await expect(entry.locator('.share-select')).toHaveCount(0)

		await pageSidebar.openShareMenu(entry)
		await expect(page.getByRole('menuitem', { name: 'Generate QR code' })).toBeVisible()
		await expect(page.getByRole('menuitem', { name: 'Customize link' })).toHaveCount(0)
		await expect(page.getByRole('menuitem', { name: 'Add another link' })).toBeVisible()
		await page.getByRole('menuitem', { name: 'Unshare' }).click()
		await expect(getToast(page, 'success', /Collective .* has been unshared/)).toBeVisible()
		await expect(entry).toHaveCount(0)
		expect(await collective.getShares(page)).toHaveLength(0)
	})

	test('Member sees share link of admin without management actions', async ({ user, page, collective, browser, baseURL }) => {
		await collective.createShare({ label: 'Newsletter', page })
		const account = await collective.addMember()
		const memberPage = await loginAsUser(browser, baseURL, account)
		try {
			await memberPage.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)
			const memberSidebar = new PageSidebarSection(memberPage)
			const sharingTab = await memberSidebar.openSidebarTab('Sharing')

			// Member can still create an own share link
			await expect(sharingTab.getByRole('button', { name: 'Create a new share link' })).toBeVisible()

			const entry = memberSidebar.getShareEntry(sharingTab, `Newsletter (by ${user.account.userId})`)
			await expect(entry.locator('.share-permission')).toHaveText('View only')
			await expect(entry.locator('.sharing-entry__copy')).toHaveCount(0)

			await memberSidebar.openShareMenu(entry)
			await expect(memberPage.getByRole('menuitem', { name: 'Generate QR code' })).toHaveCount(0)
			await expect(memberPage.getByRole('menuitem', { name: 'Customize link' })).toHaveCount(0)
			await expect(memberPage.getByRole('menuitem', { name: 'Add another link' })).toBeVisible()
			await expect(memberPage.getByRole('menuitem', { name: 'Unshare' })).toHaveCount(0)
		} finally {
			await memberPage.close()
		}
	})
})

test.describe('Collective share password protection', () => {
	test('Create share with password and open share unauthenticated', async ({ page, collective, pageSidebar, sharePage, shareEditor }) => {
		await collective.openCollective()
		const sharingTab = await pageSidebar.openSidebarTab('Sharing')

		await sharingTab.getByRole('button', { name: 'Create a new share' }).click()
		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')

		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).not.toBeChecked()

		// Enable password protection
		await settingsPanel.locator('.checkbox-radio-switch__content').filter({ hasText: 'Set password' }).click()
		await settingsPanel.getByRole('textbox', { name: 'Password' }).fill('test-password')
		await settingsPanel.getByRole('button', { name: 'Update Share' }).click()
		await expect(settingsPanel).not.toBeVisible()

		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()

		// Open password-protected share
		const shares = await collective.getShares(page)
		await sharePage.goto(shares[0].getShareUrl())
		await sharePage.getByRole('textbox', { name: 'Password' }).pressSequentially('test-password')
		await sharePage.getByRole('button', { name: 'Submit' }).click()
		await sharePage.waitForURL(/\/p\/[^/]+\/(?!authenticate)/)
		await expect(shareEditor.getContent().getByRole('heading', { name: 'Welcome' })).toBeVisible({ timeout: 15_000 })
	})

	test('Remove password via advanced settings', async ({ page, collective, pageSidebar }) => {
		const share = await collective.createShare({ password: 'test-password', page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')

		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)
		await expect(settingsPanel.getByRole('checkbox', { name: 'Set password' })).toBeChecked()

		await settingsPanel.locator('.checkbox-radio-switch__content').filter({ hasText: 'Set password' }).click()
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

		await expect(getToast(page, 'success', /Share link .* has been updated/)).toBeVisible()

		const updatedShare = await share.updateData()
		expect(updatedShare.hasPassword).toBe(true)
	})

	test('Set and remove expiration date via advanced settings', async ({ page, collective, pageSidebar }) => {
		const share = await collective.createShare({ page })
		await collective.openCollective()

		const sharingTab = await pageSidebar.openSidebarTab('Sharing')
		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')
		const settingsPanel = pageSidebar.getShareSettingsPanel(sharingTab)

		const date = new Date()
		date.setDate(date.getDate() + 3)
		const expected = date.toLocaleDateString('sv') // YYYY-MM-DD

		await settingsPanel.locator('.checkbox-radio-switch__content').filter({ hasText: 'Set expiration date' }).click()
		await settingsPanel.getByLabel('Expiration date', { exact: true }).fill(expected)
		await settingsPanel.getByRole('button', { name: 'Update share' }).click()
		await expect(settingsPanel).not.toBeVisible()
		expect((await share.updateData()).expirationDate).toBe(expected)

		await pageSidebar.clickShareMenuAction(sharingTab, 'Customize link')
		await settingsPanel.locator('.checkbox-radio-switch__content').filter({ hasText: 'Set expiration date' }).click()
		await settingsPanel.getByRole('button', { name: 'Update share' }).click()
		await expect(settingsPanel).not.toBeVisible()
		expect((await share.updateData()).expirationDate).toBeNull()
	})
})
