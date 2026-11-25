/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const test = mergeTests(createCollectiveTest, navigationTest)

type PrintWindow = Window & { __printCalls?: number }

/**
 * Stub window.print in all pages of the browser context, so the real print dialog doesn't block the test
 *
 * Counts the calls in `window.__printCalls`.
 *
 * @param page Playwright page of the browser context
 */
async function stubPrint(page: Page) {
	await page.context().addInitScript(() => {
		(window as PrintWindow).__printCalls = 0
		window.print = () => {
			(window as PrintWindow).__printCalls! += 1
		}
	})
}

/**
 * Number of window.print calls in a page with `stubPrint`
 *
 * @param page Playwright page
 */
function printCalls(page: Page) {
	return page.evaluate(() => (window as PrintWindow).__printCalls)
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

		await stubPrint(page)

		// Hold back the images until released, so printing waits for them
		let heldImages = 0
		let releaseImages = () => {}
		const imagesReleased = new Promise<void>((resolve) => {
			releaseImages = resolve
		})
		await page.route(/mediaPreview|\/core\/preview|test\.png/, async (route) => {
			if (route.request().resourceType() !== 'image') {
				return route.fallback()
			}
			heldImages += 1
			await imagesReleased
			await route.continue()
		})

		await page.goto(`/index.php/apps/collectives/_/print/${collective.getCollectiveUrlPart()}`)

		await expect(page.getByText('Preparing collective for exporting or printing'))
			.toBeVisible()
		await expect(page.getByText('Preparing collective for exporting or printing'))
			.toBeHidden({ timeout: 30000 })
		await expect.poll(() => heldImages).toBeGreaterThan(0)

		// The print dialog only opens on request
		expect(await printCalls(page)).toBe(0)
		await page.getByRole('button', { name: 'Export or print' }).click()

		// The page selection is locked while printing waits for the images
		const checkbox = page.getByRole('navigation', { name: 'Pages to export or print' })
			.getByRole('checkbox')
			.first()
		await expect(checkbox).toBeDisabled()

		releaseImages()
		await expect.poll(() => printCalls(page), { timeout: 20000 }).toBe(1)
		await expect(checkbox).toBeEnabled()

		const imgs = page.locator('div.ProseMirror figure.image img.image__main')
		const count = await imgs.count()
		expect(count).toBe(2)
		for (let i = 0; i < count; i++) {
			const naturalWidth = await imgs.nth(i).evaluate((el: HTMLImageElement) => el.naturalWidth)
			expect(naturalWidth, `image ${i} should have loaded`).toBeGreaterThan(0)
		}
	})

	test('selects the pages to export in the page list', async ({ user, page, collective, navigation }) => {
		const parent = await collective.createPage({ title: 'Parent', user, page })
		await collective.createPage({ title: 'Child', parentId: parent.data.id, user, page })
		await collective.createPage({ title: 'Other', user, page })
		await stubPrint(page)

		await collective.openCollective()
		const popupPromise = page.waitForEvent('popup')
		await navigation.clickCollectiveMenu(collective.data.name, 'Export or print')
		const printPage = await popupPromise
		const printButton = printPage.getByRole('button', { name: 'Export or print' })

		// The button is only enabled once the content of the selected pages is loaded
		await expect(printButton).toBeEnabled({ timeout: 30000 })
		const pageList = printPage.getByRole('navigation', { name: 'Pages to export or print' })
		const checkbox = (name: string) => pageList.getByRole('checkbox', { name, exact: true })
		await expect(printPage.locator('.print-page:visible h2.page-title-collective'))
			.toContainText(collective.data.name)
		// Titles of the shown subpages, sorted as sibling order depends on the creation time
		const subpageTitles = () => expect.poll(async () => (await printPage
			.locator('.print-page:visible h2.page-title:not(.page-title-collective)')
			.allTextContents()).map((title) => title.trim()).sort())
		await subpageTitles().toEqual(['Child', 'Other', 'Parent'])

		// Subpages are listed nested in their parent page
		await expect(pageList.getByRole('listitem')
			// `has` locators are resolved within the list item, so they must not start at `pageList`
			.filter({ has: printPage.getByRole('checkbox', { name: 'Parent', exact: true }) })
			.getByRole('list')
			.getByRole('checkbox', { name: 'Child', exact: true })).toBeChecked()

		// Unselecting a page unselects its subpages and hides them from the print view
		await pageList.getByText('Parent', { exact: true }).click()
		await expect(checkbox('Parent')).not.toBeChecked()
		await expect(checkbox('Child')).not.toBeChecked()
		await subpageTitles().toEqual(['Other'])

		// Subpages can still be selected individually
		await pageList.getByText('Child', { exact: true }).click()
		await subpageTitles().toEqual(['Child', 'Other'])

		await pageList.getByText('Other', { exact: true }).click()
		await subpageTitles().toEqual(['Child'])

		// The selection is kept in the URL
		await expect(printPage).toHaveURL(/[?&](pages|exclude)=/)
		await printPage.reload()
		await expect(printButton).toBeEnabled({ timeout: 30000 })
		await subpageTitles().toEqual(['Child'])
		await expect(checkbox('Other')).not.toBeChecked()

		await printButton.click()
		await expect.poll(() => printCalls(printPage)).toBe(1)
		await printPage.close()
	})

	test('waits only for the content of the selected pages', async ({ user, page, collective }) => {
		await collective.createPage({ title: 'Fast', user, page })
		await collective.createPage({ title: 'Slow', user, page })

		// Hold back the content of the page "Slow" until released
		let heldRequests = 0
		let releaseContent = () => {}
		const contentReleased = new Promise<void>((resolve) => {
			releaseContent = resolve
		})
		await page.route(/\/dav\/files\/.*\/Slow\.md(\?|$)/, async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			heldRequests += 1
			await contentReleased
			await route.continue()
		})

		await page.goto(`/index.php/apps/collectives/_/print/${collective.getCollectiveUrlPart()}`)

		const loadingScreen = page.getByText('Preparing collective for exporting or printing')
		const printButton = page.getByRole('button', { name: 'Export or print' })
		const pageList = page.getByRole('navigation', { name: 'Pages to export or print' })
		const subpageTitles = page.locator('.print-page:visible h2.page-title:not(.page-title-collective)')
		await expect.poll(() => heldRequests).toBeGreaterThan(0)
		await expect(loadingScreen).toBeVisible()
		await expect(printButton).toBeDisabled()

		// Unselecting the loading page finishes loading without it
		await pageList.getByText('Slow', { exact: true }).click()
		await expect(loadingScreen).toBeHidden({ timeout: 30000 })
		await expect(subpageTitles).toHaveText(['Fast'])
		await expect(printButton).toBeEnabled()

		// Selecting it again waits for its content
		await pageList.getByText('Slow', { exact: true }).click()
		await expect(loadingScreen).toBeVisible()
		await expect(printButton).toBeDisabled()

		releaseContent()
		await expect(loadingScreen).toBeHidden({ timeout: 30000 })
		await expect(printButton).toBeEnabled()
	})

	test('shows an empty state if no requested page exists', async ({ page, collective }) => {
		await stubPrint(page)
		await page.goto(`/index.php/apps/collectives/_/print/${collective.getCollectiveUrlPart()}?pages=999999999`)

		await expect(page.getByText('No pages to export or print')).toBeVisible()
		await expect(page.getByText('Preparing collective for exporting or printing')).toBeHidden()
	})
})
