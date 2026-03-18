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
