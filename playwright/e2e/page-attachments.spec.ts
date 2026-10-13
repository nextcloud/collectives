/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'

const test = mergeTests(createCollectiveTest, pageSidebarTest)

test.describe('Page attachments', () => {
	test('reopen the page sidebar from the viewer', async ({ user, page, collective, pageSidebar }) => {
		const collectivePage = await collective.createPage({ title: 'Page with attachment', user, page })
		await collectivePage.uploadImage({ filename: 'test.png', user, page })
		await collectivePage.open()

		await pageSidebar.openSidebarTab('Attachments')
		await pageSidebar.getAttachmentListItem('test.png')
			.getByRole('link', { name: 'test.png' })
			.click()

		const viewer = collectivePage.getViewerContent()
		const openSidebarButton = viewer.getByRole('button', { name: 'Open sidebar' })
		await expect(viewer).toBeVisible()
		// Viewer only offers its sidebar button while the page sidebar is hidden
		await expect(openSidebarButton).toHaveCount(0)

		await pageSidebar.openSidebarTab('Versions')
		await pageSidebar.closeSidebar()
		await openSidebarButton.click()

		await expect(pageSidebar.el).toBeVisible()
		await expect(pageSidebar.el.getByRole('tab', { name: 'Attachments' }))
			.toHaveAttribute('aria-selected', 'true')
		await expect(viewer).toBeVisible()
	})
})
