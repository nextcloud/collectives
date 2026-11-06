/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const test = mergeTests(createCollectiveTest, navigationTest)

/**
 * Stub window.print in all pages of the browser context, so the real print dialog doesn't block the test
 *
 * @param page Playwright page of the browser context
 */
async function stubPrint(page: Page) {
	await page.context().addInitScript(() => {
		window.print = () => {}
	})
}

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

	test('exports the pages selected in the export dialog', async ({ user, page, collective, navigation }) => {
		const parent = await collective.createPage({ title: 'Parent', user, page })
		await collective.createPage({ title: 'Child', parentId: parent.data.id, user, page })
		const other = await collective.createPage({ title: 'Other', user, page })
		await stubPrint(page)

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Export or print')

		const dialog = page.getByRole('dialog', { name: 'Export or print' })
		await expect(dialog).toBeVisible()
		await expect(dialog.getByRole('checkbox', { name: 'Select all pages' })).toBeChecked()
		for (const title of ['Parent', 'Child', 'Other']) {
			await expect(dialog.getByRole('checkbox', { name: title, exact: true })).toBeChecked()
		}

		// Subpages are listed nested in their parent page
		await expect(dialog.getByRole('listitem')
			.filter({ has: page.getByRole('checkbox', { name: 'Parent', exact: true }) })
			.getByRole('list')
			.getByRole('checkbox', { name: 'Child', exact: true })).toBeVisible()

		await dialog.getByText('Other', { exact: true }).click()
		await expect(dialog.getByRole('checkbox', { name: 'Other', exact: true })).not.toBeChecked()
		await expect(dialog.getByRole('checkbox', { name: 'Select all pages' })).not.toBeChecked()

		const popupPromise = page.waitForEvent('popup')
		await dialog.getByRole('button', { name: 'Export or print' }).click()
		const printPage = await popupPromise
		await expect(dialog).toBeHidden()

		// Only one page is unselected, so the URL lists that one instead of the selected ones
		expect(new URL(printPage.url()).searchParams.get('exclude')).toBe(String(other.data.id))

		await expect(printPage.getByText('Preparing collective for exporting or printing'))
			.toBeHidden({ timeout: 30000 })
		const titles = printPage.locator('h2.page-title')
		await expect(titles).toHaveText([new RegExp(collective.data.name), 'Parent', 'Child'])
		await printPage.close()
	})

	test('shows an empty state if no requested page exists', async ({ page, collective }) => {
		await stubPrint(page)
		await page.goto(`/index.php/apps/collectives/_/print/${collective.getCollectiveUrlPart()}?pages=999999999`)

		await expect(page.getByText('No pages to export or print')).toBeVisible()
		await expect(page.getByText('Preparing collective for exporting or printing')).toBeHidden()
	})
})
