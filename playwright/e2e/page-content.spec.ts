/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'

const test = mergeTests(createCollectiveTest, editorTest)

test.describe('Page content', () => {
	test('create whiteboard from attachments menu', async ({ user, page, collective, editor }) => {
		test.slow()
		await runOcc(['app:enable', '--force', 'whiteboard'])
		const collectivePage = await collective.createPage({ title: 'Page with whiteboard', user, page })
		await collectivePage.open(true)

		editor.setMode(true)
		await editor.clickMenu('attachment', 'New whiteboard')
		await expect(editor.getContent()
			.locator('.widget-file.whiteboard'))
			.toBeVisible()

		await runOcc(['app:disable', 'whiteboard'])
	})

	test('editor container grows vertically', async ({ user, page, collective, editor }) => {
		const collectivePage = await collective.createPage({ title: 'Page', user, page })
		await collectivePage.open(true)

		editor.setMode(true)
		await expect(editor.getContent()).toBeVisible()
		await expect(editor.menubar).toBeVisible()

		const containerBox = (await page.locator('.page-scroll-container').boundingBox())!
		const menubarBox = (await editor.menubar.boundingBox())!
		const contentBox = (await editor.getContent().boundingBox())!
		const suggestionsContainerBox = (await editor.suggestionsContainer.boundingBox())!

		const expectedContentHeight = containerBox.height - menubarBox.height - suggestionsContainerBox.height

		// Allow up to 2px tolerance for borders etc.
		expect(Math.abs(expectedContentHeight - contentBox.height)).toBeLessThan(2)
	})

	test('link to page from link menu', async ({ user, page, collective, editor }) => {
		const sourcePage = await collective.createPage({ title: 'Source page', user, page })
		const targetPage = await collective.createPage({ title: 'Target page', user, page })
		await sourcePage.open(true)

		editor.setMode(true)
		await editor.clickMenu('Insert link', 'Link to page')
		await editor.smartPickerSearch.pressSequentially('Target page')

		await editor.smartPicker.locator('.search-result').filter({ hasText: 'Target page' }).click()

		const pageWidget = editor.getContent().locator('.widget-custom a.collective-page')

		await expect(pageWidget).toBeVisible()
		const origin = new URL(page.url()).origin
		await expect(pageWidget).toHaveAttribute('href', origin + targetPage.getPageUrl())
	})
})
