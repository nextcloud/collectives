/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../support/fixtures/create-collectives.ts'
import { observeTextEditors, waitForTextEditors } from '../support/helpers/textEditor.ts'
import { webdavUrl } from '../support/helpers/urls.ts'
import { PageSidebarSection } from '../support/sections/PageSidebarSection.ts'

for (const action of ['Rename', 'Delete']) {
	test(`${action} embedded attachment from fresh preview persists file and references`, async ({ user, page, collective }) => {
		await observeTextEditors(page)
		const cp = await collective.createPage({ title: `Attachment ${action}`, content: 'Image page', user, page })
		const src = await cp.uploadImage({ filename: 'test.png', user, page })
		await cp.setContent({ content: `Image page\n\n![test](${src})`, user, page })
		await cp.open(false)
		await waitForTextEditors(page)
		const sidebar = new PageSidebarSection(page)
		const tab = await sidebar.openSidebarTab('Attachments')
		const item = tab.locator('.attachment').filter({ hasText: 'test.png' })
		await item.getByRole('button', { name: 'Actions', exact: true }).click()
		await page.getByRole('menuitem', { name: action, exact: true }).click()
		if (action === 'Rename') {
			await page.getByRole('textbox', { name: 'Attachment name' }).fill('renamed.png')
			await page.getByRole('button', { name: 'Rename attachment', exact: true }).click()
		}
		const markdownUrl = webdavUrl(user.account.userId, cp.data.collectivePath, cp.data.filePath, cp.data.fileName)
		const oldUrl = webdavUrl(user.account.userId, cp.data.collectivePath, cp.data.filePath, src)
		await expect.poll(async () => {
			const response = await page.request.get(markdownUrl)
			return response.ok() ? !(await response.text()).includes(src) : false
		}).toBe(true)
		await expect.poll(async () => (await page.request.get(oldUrl)).status()).toBe(404)
		if (action === 'Rename') {
			const newUrl = oldUrl.replace('test.png', 'renamed.png')
			expect((await page.request.get(newUrl)).status()).toBe(200)
			await expect.poll(async () => {
				const response = await page.request.get(markdownUrl)
				return response.ok() ? response.text() : 'waiting for readable Markdown'
			}).toContain('renamed.png')
		}
		await page.reload()
		await cp.waitForContent(false)
		if (action === 'Rename') {
			await expect(cp.getContent().locator('img')).toHaveAttribute('src', /renamed\.png/)
		} else {
			await expect(cp.getContent().locator('img')).toHaveCount(0)
		}
	})
}

test('embedded attachment classification survives preview edit preview and reload', async ({ user, page, collective }) => {
	const cp = await collective.createPage({ title: 'Attachment classification', content: 'Image page', user, page })
	const src = await cp.uploadImage({ filename: 'test.png', user, page })
	await cp.setContent({ content: `Image page\n\n![test](${src})`, user, page })
	await cp.open(false)
	const tab = await new PageSidebarSection(page).openSidebarTab('Attachments')
	const embedded = tab.locator('.attachment-list-embedded .attachment').filter({ hasText: 'test.png' })
	await expect(embedded).toBeVisible()
	await embedded.getByRole('button', { name: 'Actions', exact: true }).click()
	await expect(page.getByRole('menuitem', { name: 'View in page', exact: true })).toBeVisible()
	await page.keyboard.press('Escape')
	await cp.switchMode(true)
	await cp.switchMode(false)
	await expect(embedded).toBeVisible()
	await page.reload()
	await expect(embedded).toBeVisible()
})

test('failed editor initialization prevents attachment deletion and permits retry', async ({ user, page, collective }) => {
	await observeTextEditors(page)
	const cp = await collective.createPage({ title: 'Initialization failure', content: 'Image page', user, page })
	const src = await cp.uploadImage({ filename: 'test.png', user, page })
	await cp.setContent({ content: `Image page\n\n![test](${src})`, user, page })
	await cp.open(false)
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.failNext = true
	})
	const tab = await new PageSidebarSection(page).openSidebarTab('Attachments')
	const item = tab.locator('.attachment').filter({ hasText: 'test.png' })
	await item.getByRole('button', { name: 'Actions', exact: true }).click()
	await page.getByRole('menuitem', { name: 'Delete', exact: true }).click()
	await expect(page.getByText('Failed to delete attachment')).toBeVisible()
	const fileUrl = webdavUrl(user.account.userId, cp.data.collectivePath, cp.data.filePath, src)
	expect((await page.request.get(fileUrl)).status()).toBe(200)
	await item.getByRole('button', { name: 'Actions', exact: true }).click()
	await page.getByRole('menuitem', { name: 'Delete', exact: true }).click()
	await expect.poll(async () => (await page.request.get(fileUrl)).status()).toBe(404)
	await page.reload()
	await expect(cp.getContent().locator('img')).toHaveCount(0)
})

test('failed preflight save prevents attachment deletion', async ({ user, page, collective }) => {
	await observeTextEditors(page)
	const cp = await collective.createPage({ title: 'Save failure', content: 'Image page', user, page })
	const src = await cp.uploadImage({ filename: 'test.png', user, page })
	await cp.setContent({ content: `Image page\n\n![test](${src})`, user, page })
	await cp.open(false)
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.failSave = true
	})
	const tab = await new PageSidebarSection(page).openSidebarTab('Attachments')
	await tab.locator('.attachment').filter({ hasText: 'test.png' }).getByRole('button', { name: 'Actions', exact: true }).click()
	await page.getByRole('menuitem', { name: 'Delete', exact: true }).click()
	await expect(page.getByText('Failed to delete attachment')).toBeVisible()
	const fileUrl = webdavUrl(user.account.userId, cp.data.collectivePath, cp.data.filePath, src)
	expect((await page.request.get(fileUrl)).status()).toBe(200)
	await page.reload()
	await expect(cp.getContent().locator('img')).toBeVisible()
})

test('failed final save reports partial rename and can be saved again', async ({ user, page, collective }) => {
	await observeTextEditors(page)
	const cp = await collective.createPage({ title: 'Final save failure', content: 'Image page', user, page })
	const src = await cp.uploadImage({ filename: 'test.png', user, page })
	await cp.setContent({ content: `Image page\n\n![test](${src})`, user, page })
	await cp.open(false)
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.failSaveAfter = 1
	})
	const tab = await new PageSidebarSection(page).openSidebarTab('Attachments')
	await tab.locator('.attachment').filter({ hasText: 'test.png' }).getByRole('button', { name: 'Actions', exact: true }).click()
	await page.getByRole('menuitem', { name: 'Rename', exact: true }).click()
	await page.getByRole('textbox', { name: 'Attachment name' }).fill('renamed.png')
	await page.getByRole('button', { name: 'Rename attachment', exact: true }).click()
	await expect(page.getByText('The attachment was renamed, but the page could not be saved. Keep the editor open and try saving again.')).toBeVisible()
	await expect(page.locator('.toastify').filter({ hasText: /^Renamed attachment/ })).toHaveCount(0)
	await page.locator('.toastify').filter({ hasText: 'The attachment was renamed, but the page could not be saved.' }).getByRole('button', { name: 'Close', exact: true }).click()
	await expect(cp.getContent(true)).toBeVisible()
	const fileUrl = webdavUrl(user.account.userId, cp.data.collectivePath, cp.data.filePath, src.replace('test.png', 'renamed.png'))
	expect((await page.request.get(fileUrl)).status()).toBe(200)
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.failSaveAfter = -1
	})
	await cp.switchMode(false)
	const markdownUrl = webdavUrl(user.account.userId, cp.data.collectivePath, cp.data.filePath, cp.data.fileName)
	await expect.poll(async () => {
		const response = await page.request.get(markdownUrl)
		return response.ok() ? response.text() : 'waiting for readable Markdown'
	}).toContain('renamed.png')
	await page.reload()
	await expect(cp.getContent().locator('img')).toHaveAttribute('src', /renamed\.png/)
})
