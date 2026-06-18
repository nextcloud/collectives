/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect } from '@playwright/test'
import { test } from '../support/fixtures/create-collectives.ts'

test.describe('Collective print view', () => {
	test('loads all images before opening print dialog', async ({ user, page, collective }) => {
		// Create two pages each with an image. The second page is below initial viewport.
		for (let i = 0; i < 2; i++) {
			const collectivePage = await collective.createPage({
				title: `Page with image ${i}`,
				user,
				page,
			})
			const src = await collectivePage.uploadImage({
				filename: 'test.png',
				user,
				page,
			})
			await collectivePage.setContent({
				content: `# Image ${i}\n\n![image ${i}](${src})\n`,
				user,
				page,
			})
		}

		// Stub window.print so the real print dialog doesn't block the test
		await page.addInitScript(() => {
			(window as Window & { __printCalls?: number }).__printCalls = 0
			window.print = () => {
				(window as Window & { __printCalls?: number }).__printCalls! += 1
			}
		})

		await page.goto(`/index.php/apps/collectives/_/print/${collective.getCollectiveUrlPart()}`)

		await expect(page.getByText('Preparing collective for exporting or printing'))
			.toBeVisible()
		await expect(page.getByText('Preparing collective for exporting or printing'))
			.toBeHidden({ timeout: 30000 })

		const printCalls = await page.evaluate(() => (window as Window & { __printCalls?: number }).__printCalls)
		expect(printCalls).toBeGreaterThan(0)

		const imgs = page.locator('div.ProseMirror figure.image img.image__main')
		const count = await imgs.count()
		expect(count).toBe(2)
		for (let i = 0; i < count; i++) {
			const naturalWidth = await imgs.nth(i).evaluate((el: HTMLImageElement) => el.naturalWidth)
			expect(naturalWidth, `image ${i} should have loaded`).toBeGreaterThan(0)
		}
	})
})
