/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { resolve } from 'node:path'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'

const test = mergeTests(createCollectiveTest, editorTest, pageSidebarTest)

const fixturePath = (name: string) => resolve(import.meta.dirname, '../support/fixtures/files', name)

test.describe('Page attachments', () => {
	test('list an image inserted in the editor as embedded', async ({ user, page, collective, editor, pageSidebar }) => {
		const collectivePage = await collective.createPage({ title: 'Page', user, page })
		await collectivePage.open(true)
		editor.setMode(true)

		const upload = page.waitForResponse((response) => response.url().includes('/text/attachment/upload') && response.ok())
		await editor.editor
			.locator('input[data-text-el="attachment-file-input"]')
			.setInputFiles(fixturePath('test.png'))
		await upload
		await expect(editor.getContent().locator('[data-component="image-view"]')).toBeVisible()

		await editor.switchMode(false)
		await expect(editor.getContent().locator('img.image__main')).toBeVisible()

		await pageSidebar.openSidebarTab('Attachments')
		await expect(pageSidebar.getAttachmentList('embedded')).toContainText('test.png')
		await expect(pageSidebar.getAttachmentList('not-embedded')).toHaveCount(0)
	})

	test('list an image removed from the editor as not embedded', async ({ user, page, collective, editor, pageSidebar }) => {
		const collectivePage = await collective.createPage({ title: 'Page', user, page })
		const imagePath = await collectivePage.uploadImage({ filename: 'test.png', user, page })
		await collectivePage.setContent({ content: `![test.png](${imagePath})`, user, page })
		await collectivePage.open()
		await editor.switchMode(true)

		await pageSidebar.openSidebarTab('Attachments')
		await expect(pageSidebar.getAttachmentList('embedded')).toContainText('test.png')
		await expect(pageSidebar.getAttachmentList('not-embedded')).toHaveCount(0)

		await editor.getContent().locator('[data-component="image-view"] .image__view').hover()
		await editor.getContent().getByRole('button', { name: 'Delete this image' }).click()
		await expect(editor.getContent().locator('[data-component="image-view"]')).toHaveCount(0)

		await expect(pageSidebar.getAttachmentList('not-embedded')).toContainText('test.png')
		await expect(pageSidebar.getAttachmentList('embedded')).toHaveCount(0)

		await pageSidebar.clickAttachmentAction('test.png', 'Delete')
		await expect(pageSidebar.getAttachmentList('not-embedded')).toHaveCount(0)
		await expect(pageSidebar.getAttachmentList('deleted')).toContainText('test.png')
	})

	test('upload, rename, delete and restore an attachment in the sidebar', async ({ user, page, collective, pageSidebar }) => {
		const collectivePage = await collective.createPage({ title: 'Page', user, page })
		await collectivePage.open()

		const tab = await pageSidebar.openSidebarTab('Attachments')
		await tab.locator('.upload-area input[type="file"]').setInputFiles(fixturePath('test.pdf'))
		await expect(pageSidebar.getAttachmentList('not-embedded')).toContainText('test.pdf')

		await pageSidebar.clickAttachmentAction('test.pdf', 'Rename')
		const renameDialog = page.getByRole('dialog', { name: 'Rename attachment' })
		await renameDialog.getByRole('textbox', { name: 'Attachment name' }).fill('renamed.pdf')
		await renameDialog.getByRole('textbox', { name: 'Attachment name' }).press('Enter')
		await expect(pageSidebar.getAttachmentList('not-embedded')).toContainText('renamed.pdf')

		await pageSidebar.clickAttachmentAction('renamed.pdf', 'Delete')
		await expect(pageSidebar.getAttachmentList('not-embedded')).toHaveCount(0)
		await expect(pageSidebar.getAttachmentList('deleted')).toContainText('renamed.pdf')

		await pageSidebar.getAttachmentListItem('renamed.pdf')
			.getByRole('button', { name: 'Restore' })
			.click()
		await expect(pageSidebar.getAttachmentList('not-embedded')).toContainText('renamed.pdf')
		await expect(pageSidebar.getAttachmentList('deleted')).toHaveCount(0)
	})

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
