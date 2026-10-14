/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { observeTextEditors, waitForTextEditors } from '../support/helpers/textEditor.ts'

const test = mergeTests(createCollectiveTest, editorTest)

test.describe('Page content', () => {
	test('preview does not lock the page for WebDAV writers', async ({ user, page, collective }) => {
		await observeTextEditors(page)
		const collectivePage = await collective.createPage({
			title: 'Preview without a writable Text session',
			content: 'Original content',
			user,
			page,
		})
		await collectivePage.open(false)
		await expect(collectivePage.getContent()).toContainText('Original content')
		await waitForTextEditors(page)
		// A hidden writable editor would acquire a Text lock and reject this PUT.
		await collectivePage.setContent({ content: 'Updated through WebDAV', user, page })
		const writableCalls = await page.evaluate(() => {
			return (window as any).collectivesTextProbe.calls.filter((call: { writable: boolean }) => call.writable).length
		})
		expect(writableCalls).toBe(0)
		await page.reload()
		await collectivePage.waitForContent(false)
		await expect(collectivePage.getContent()).toContainText('Updated through WebDAV')
		// Lazy initialization must still allow switching from reading to editing.
		await collectivePage.switchMode(true)
		await expect(collectivePage.getContent(true)).toContainText('Updated through WebDAV')
		await collectivePage.getContent(true).fill('Edited after entering edit mode')
		await collectivePage.switchMode(false)
		await expect(collectivePage.getContent()).toContainText('Edited after entering edit mode')
		await collectivePage.switchMode(true)
		await expect(collectivePage.getContent(true)).toContainText('Edited after entering edit mode')
	})

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

	test('mentioning lists collective members first', async ({ user, page, collective, editor }) => {
		const extraMemberIds = []
		for (let i = 0; i < 2; i++) {
			const member = await collective.addMember()
			extraMemberIds.push(member.userId)
		}
		const collectivePage = await collective.createPage({ title: 'Page', user, page })
		await collectivePage.open(true)

		editor.setMode(true)
		await editor.getContent().fill('@')
		await expect(editor.getMentionSuggestions({ limit: 3 })).toHaveText([
			user.account.userId,
			...extraMemberIds.sort(),
		])
	})
})
