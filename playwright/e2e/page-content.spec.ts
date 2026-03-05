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
})
