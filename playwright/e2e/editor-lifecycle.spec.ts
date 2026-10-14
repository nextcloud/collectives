/**
 * SPDX-FileCopyrightText: 2026 Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../support/fixtures/create-collectives.ts'
import { observeTextEditors } from '../support/helpers/textEditor.ts'

test('leaving during editor initialization does not affect the next page', async ({ user, page, collective }) => {
	const pageErrors: string[] = []
	page.on('pageerror', (error) => pageErrors.push(error.message))
	await observeTextEditors(page)
	const first = await collective.createPage({ title: 'Source', content: 'Source content', user, page })
	const second = await collective.createPage({ title: 'Destination', content: 'Destination content', user, page })
	await first.open(false)
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.holdNext = true
	})
	await first.getModeButton(true).click()
	await page.waitForFunction(() => {
		return (window as any).collectivesTextProbe.release !== null
	})
	await page.evaluate(async (path) => {
		await (window as any).OCA.Collectives.vueRouter.push(path)
	}, second.getPageUrl().replace('/index.php/apps/collectives', ''))
	await expect(second.getContent()).toContainText('Destination content')
	await second.switchMode(true)
	await expect(second.getContent(true)).toContainText('Destination content')
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.release()
	})
	await page.waitForFunction((id) => {
		return (window as any).collectivesTextProbe.calls.some((call: { fileId: number, writable: boolean, resolved: boolean }) => call.fileId === id && call.writable && call.resolved)
	}, first.data.id)
	await expect(second.getContent(true)).toContainText('Destination content')
	await second.getContent(true).fill('Destination saved after navigation')
	await second.switchMode(false)
	await expect(second.getContent()).toContainText('Destination saved after navigation')
	await page.reload()
	await expect(second.getContent()).toContainText('Destination saved after navigation')
	await first.open(false)
	await expect(first.getContent()).toContainText('Source content')
	expect(pageErrors).toEqual([])
})

test('editor initialization rejection can be retried without navigation', async ({ user, page, collective }) => {
	const pageErrors: string[] = []
	page.on('pageerror', (error) => pageErrors.push(error.message))
	await observeTextEditors(page)
	const cp = await collective.createPage({ title: 'Retry editor', content: 'Initial content', user, page })
	await cp.open(false)
	await page.evaluate(() => {
		(window as any).collectivesTextProbe.failNext = true
	})
	await cp.getModeButton(true).click()
	await expect(page.getByText('Could not load the editor. Please try again.')).toBeVisible()
	await page.locator('.toastify').filter({ hasText: 'Could not load the editor.' }).getByRole('button', { name: 'Close', exact: true }).click()
	await cp.switchMode(true)
	await cp.getContent(true).fill('Saved after retry')
	await cp.switchMode(false)
	await expect(cp.getContent()).toContainText('Saved after retry')
	await page.reload()
	await expect(cp.getContent()).toContainText('Saved after retry')
	expect(pageErrors).toEqual([])
})
