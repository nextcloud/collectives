/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { Collective } from '../support/fixtures/Collective.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'
import { test as titleBarTest } from '../support/fixtures/titleBar.ts'

const test = mergeTests(createCollectiveTest, editorTest, navigationTest, pageListTest, titleBarTest)

/**
 * Open the collective and wait for its templates to be loaded.
 * Without templates, adding a page skips the template picker.
 *
 * @param collective the collective to open
 * @param page the Playwright page
 */
async function openCollectiveWithTemplates(collective: Collective, page: Page) {
	const templatesLoaded = page.waitForResponse((response) => response.request().method() === 'GET'
		&& /\/pages\/templates(\?|$)/.test(response.url()))
	await collective.openCollective()
	await templatesLoaded
}

test.describe('Page templates', () => {
	test('Create a new template', async ({ page, collective, navigation }) => {
		await openCollectiveWithTemplates(collective, page)
		await navigation.clickCollectiveMenu(collective.data.name, 'Manage templates')
		const templatesDialog = page.getByRole('dialog', { name: 'Templates' })

		// Create template
		await templatesDialog.getByRole('button', { name: 'Add a template' }).click()

		// Second locator can be removed once we only support Nextcloud 36+
		const viewer = page.locator('.viewer__modal, #viewer[role="dialog"]')
		const viewerEditor = viewer.locator('.ProseMirror[contenteditable="true"]')
		await expect(viewerEditor).toBeVisible()
		// Wait until the viewer stopped animating and the editor has focus,
		// otherwise keystrokes reach the focused 'Add a template' button
		await viewerEditor.click()
		await expect(viewerEditor).toBeFocused()
		await viewerEditor.pressSequentially('## Template Heading')
		await viewerEditor.press('Enter')
		await viewerEditor.pressSequentially('Template body')
		await viewer.locator('button.header-close').click()

		// Rename template
		const templateItem = templatesDialog.locator('.template-list-item')
		await expect(templateItem).toHaveCount(1)
		await templateItem.getByRole('button', { name: 'Actions' }).click()
		await page.getByRole('menuitem', { name: 'Rename' }).click()
		await templateItem.getByRole('textbox').fill('Supertemplate')
		await templateItem.getByRole('textbox').press('Enter')
		await expect(templateItem).toContainText('Supertemplate')

		// Set emoji
		await templateItem.getByRole('button', { name: 'Select emoji for template' }).click()
		await page.locator('.emoji-mart-scroll .emoji-mart-emoji')
			.filter({ hasText: '🥰' })
			.first()
			.click()
		await expect(templateItem.locator('.template-list-item-icon')).toContainText('🥰')

		await templatesDialog.getByRole('button', { name: 'Close' }).click()
		await expect(templatesDialog).not.toBeVisible()
	})

	test.describe('with existing template', () => {
		test.beforeEach(async ({ page, user, collective }) => {
			await collective.createTemplate({
				title: 'Supertemplate',
				emoji: '🥰',
				content: '## Template Heading\n\nTemplate body\n',
				user,
				page,
			})
			await openCollectiveWithTemplates(collective, page)
		})

		test('Create a new page from template', async ({ page, editor, pageList, titleBar }) => {
			await pageList.addTopLevelPage()
			await page.getByRole('dialog', { name: 'Pick a template' })
				.getByRole('link', { name: 'Supertemplate' })
				.click()

			await expect(editor.editor).toBeVisible()
			await expect(editor.editor).toContainText('Template body')
			await expect(titleBar.el.locator('.page-title-icon')).toContainText('🥰')
		})

		test('Create a new blank page', async ({ page, editor, pageList }) => {
			await pageList.addTopLevelPage()
			await page.getByRole('dialog', { name: 'Pick a template' })
				.getByRole('link', { name: 'Blank page' })
				.click()

			await expect(editor.contentLocator(true)).toBeVisible()
			await expect(editor.editor).not.toContainText('Template body')
		})

		test('Delete template', async ({ page, collective, navigation }) => {
			await navigation.clickCollectiveMenu(collective.data.name, 'Manage templates')
			const templatesDialog = page.getByRole('dialog', { name: 'Templates' })

			const templateItem = templatesDialog.locator('.template-list-item')
				.filter({ hasText: 'Supertemplate' })
			await templateItem.getByRole('button', { name: 'Actions' }).click()
			await page.getByRole('menuitem', { name: 'Delete' }).click()
			await expect(templatesDialog.locator('.template-list-item')).toHaveCount(0)

			await templatesDialog.getByRole('button', { name: 'Close' }).click()
			await expect(templatesDialog).not.toBeVisible()
		})
	})
})
