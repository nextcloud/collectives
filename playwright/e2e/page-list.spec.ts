/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'
import { randomString } from '../support/helpers/randomString.ts'

const test = mergeTests(createCollectiveTest, editorTest, pageListTest)

test.describe('Page list', () => {
	test('sorts pages by custom order, title and time and persists the sort order', async ({ page, user, collective, pageList }) => {
		await collective.createPage({ title: 'Apple', user, page })
		// Page timestamps have a resolution of one second
		await page.waitForTimeout(1100)
		await collective.createPage({ title: 'Banana', user, page })
		await collective.createPage({ title: 'Cherry', user, page })

		await collective.openCollective()
		await pageList.expectPageListOrder(['Cherry', 'Banana', 'Apple'])

		const pageOrderSaved = page.waitForResponse((response) => response.url().includes('/userSettings/pageOrder') && response.ok())
		await pageList.sortBy('Sort ascending by title')
		await pageList.expectPageListOrder(['Apple', 'Banana', 'Cherry'])
		await pageOrderSaved

		await page.reload()
		await pageList.expectPageListOrder(['Apple', 'Banana', 'Cherry'])

		await pageList.sortBy('Sort recently changed first')
		await expect(pageList.pageListItems.last()).toContainText('Apple')

		await page.getByRole('button', { name: 'Switch back to default sort order' }).click()
		await expect(page.getByRole('button', { name: 'Switch back to default sort order' })).toBeHidden()
		await pageList.expectPageListOrder(['Cherry', 'Banana', 'Apple'])
	})

	for (const { action, keepsSource } of [{ action: 'Move', keepsSource: false }, { action: 'Copy', keepsSource: true }]) {
		test(`${action.toLowerCase()} page to a subpage`, async ({ page, user, collective, pageList }) => {
			const title = `${action} me`
			const target = await collective.createPage({ title: 'Target', user, page })
			await collective.createPage({ title: 'Target Subpage', parentId: target.data.id, user, page })
			await collective.createPage({ title, user, page })

			await collective.openCollective()
			await pageList.clickPageAction(title, 'Move or copy')
			const dialog = page.getByRole('dialog', { name: 'Copy or move page' })
			await dialog.locator('.page-list').getByText('Target', { exact: true }).click()
			await dialog.getByRole('button', { name: 'Move page down' }).click()
			await dialog.getByRole('button', { name: `${action} page here` }).click()
			await expect(dialog).toBeHidden()

			await pageList.openPage('Target')
			await expect(pageList.getSubpageItems('Target')).toContainText(['Target Subpage', title])
			await expect(pageList.getPageItem(title)).toHaveCount(keepsSource ? 2 : 1)
		})
	}

	test('shows only (sub)pages matching the filter string', async ({ page, user, collective, pageList }) => {
		const day1 = await collective.createPage({ title: 'Day 1', user, page })
		const subpage = await collective.createPage({ title: 'Subpage Title', parentId: day1.data.id, user, page })
		await collective.createPage({ title: 'DeepSubpage Title', parentId: subpage.data.id, user, page })
		await collective.createPage({ title: 'Day 2', user, page })
		await collective.createPage({ title: 'Page Title', user, page })

		await collective.openCollective()
		await pageList.filter.fill('Title')
		await expect(page.locator('.app-navigation-entry__name').filter({ visible: true }))
			.toHaveText(['Page Title', 'Subpage Title', 'DeepSubpage Title'])
	})

	test('adds and removes page as favorite', async ({ page, user, collective, pageList }) => {
		await collective.createPage({ title: 'Day 1', user, page })

		await collective.openCollective()
		await expect(pageList.favorites).toHaveCount(0)

		await pageList.clickPageAction('Day 1', 'Add to favorites')
		await expect(pageList.favoritesList).toContainText('Day 1')

		const toggleButton = pageList.favorites.getByRole('button', { name: 'Toggle favorites' })
		await toggleButton.click()
		await expect(pageList.favoritesList).toBeHidden()
		await toggleButton.click()
		await expect(pageList.favoritesList).toBeVisible()

		await pageList.clickPageAction('Day 1', 'Remove from favorites')
		await expect(pageList.favorites).toHaveCount(0)
	})

	test('trashes and restores page with subpage and attachment', async ({ page, user, collective, pageList, editor }) => {
		const created = await collective.createPage({ title: 'Day 1', user, page })
		await collective.createPage({ title: 'Subpage', parentId: created.data.id, user, page })
		// Adding a subpage turns the page into a folder, so reload its file path
		collective.collectivePages = await collective.getPages()
		const day1 = collective.getPageByTitle('Day 1')
		const imagePath = await day1.uploadImage({ filename: 'test.png', user, page })
		await day1.setContent({ content: `![test.png](${imagePath})`, user, page })

		await collective.openCollective()
		await pageList.clickPageAction('Day 1', 'Delete page and subpages')
		await expect(pageList.getPageItem('Day 1')).toHaveCount(0)

		await page.getByRole('button', { name: 'Show deleted pages' }).click()
		const trash = page.getByRole('dialog', { name: 'Deleted pages' })
		await trash.getByRole('row', { name: /Day 1/ })
			.getByRole('button', { name: 'Restore page' })
			.click()
		await expect(trash.getByText('No deleted pages.')).toBeVisible()
		await trash.getByRole('button', { name: 'Close' }).click()

		await pageList.openPage('Day 1')
		await expect(pageList.getSubpageItems('Day 1')).toContainText(['Subpage'])
		await expect(editor.contentLocator(false).locator('img.image__main')).toBeVisible()
	})
})

test.describe('Page list move and copy to another collective', () => {
	test.use({
		// eslint-disable-next-line no-empty-pattern
		collectiveConfigs: async ({}, use) => use([
			{ name: randomString() },
			{ name: randomString() },
		]),
	})

	for (const { action, keepsSource } of [{ action: 'Move', keepsSource: false }, { action: 'Copy', keepsSource: true }]) {
		test(`${action.toLowerCase()} page to a subpage in another collective`, async ({ page, user, collectives, pageList }) => {
			const [source, destination] = collectives
			const title = `${action} me`
			const target = await destination.createPage({ title: 'Target', user, page })
			await destination.createPage({ title: 'Target Subpage', parentId: target.data.id, user, page })
			await source.createPage({ title, user, page })

			await source.openCollective()
			await pageList.clickPageAction(title, 'Move or copy')
			const dialog = page.getByRole('dialog', { name: 'Copy or move page' })
			const browserList = dialog.locator('.page-list')
			await dialog.getByRole('button', { name: 'Breadcrumb for list of collectives' }).click()
			await browserList.getByText(destination.data.name, { exact: true }).click()
			await browserList.getByText('Target', { exact: true }).click()
			await dialog.getByRole('button', { name: 'Move page down' }).click()
			await dialog.getByRole('button', { name: `${action} page to ${destination.data.name}` }).click()
			await expect(dialog).toBeHidden()
			await expect(pageList.getPageItem(title)).toHaveCount(keepsSource ? 1 : 0)

			await destination.openCollective()
			await pageList.openPage('Target')
			await expect(pageList.getSubpageItems('Target')).toContainText(['Target Subpage', title])
		})
	}
})

test.describe('Page list drag and drop', () => {
	let page1: CollectivePage
	let page2: CollectivePage
	let page3: CollectivePage

	test.beforeEach(async ({ page, user, collective }) => {
		page1 = await collective.createPage({ title: 'Page 1', user, page })
		page2 = await collective.createPage({ title: 'Page 2', user, page })
		page3 = await collective.createPage({ title: 'Page 3', user, page })
	})

	test('Reorder pages', async ({ page, collective, pageList }) => {
		await collective.openCollective()
		await expect(pageList.el).toBeVisible()
		await pageList.expectPageListOrder([page3.data.title, page2.data.title, page1.data.title])

		const page1El = pageList.getPageItem(page1.data.title)
		const page2El = pageList.getPageItem(page2.data.title)

		await page1El.hover()
		await page.mouse.down()
		await page2El.hover({ position: { x: 10, y: 5 } })
		// Wait for sortable.js animation to move target
		await expect(page2El).toHaveCSS('transform', 'none')
		await page.mouse.up()
		await pageList.expectPageListOrder([page3.data.title, page1.data.title, page2.data.title])
	})

	test('Move page into another page', async ({ page, collective, pageList }) => {
		await collective.openCollective()
		await expect(pageList.el).toBeVisible()
		await pageList.expectPageListOrder([page3.data.title, page2.data.title, page1.data.title])

		const page1El = pageList.getPageItem(page1.data.title)
		const page3El = pageList.getPageItem(page3.data.title)

		await page1El.hover()
		await page.mouse.down()
		// Trigger `hover()` two times, see https://playwright.dev/docs/input#dragging-manually —
		// only the second one dispatches the `dragover` that arms the drop targe
		await page3El.hover()
		await page3El.hover()
		// Wait for drop target to have the class (sortable.js 20ms delay)
		await expect(page3El).toHaveClass(/highlight-target/)
		await page.mouse.up()
		await pageList.expectPageListOrder([page3.data.title, page1.data.title, page2.data.title])
	})

	test('Move page into subpage of expanded page', async ({ page, user, collective, pageList }) => {
		const subpage = await collective.createPage({ title: 'Subpage', parentId: page3.data.id, user, page })

		await collective.openCollective()
		await expect(pageList.el).toBeVisible()
		await pageList.toggleExpandPage(page3.data.title)
		await pageList.expectPageListOrder([page3.data.title, subpage.data.title, page2.data.title, page1.data.title])

		const page1El = pageList.getPageItem(page1.data.title)
		const subpageEl = pageList.getPageItem(subpage.data.title)

		await page1El.hover()
		await page.mouse.down()
		// Trigger `hover()` two times, see https://playwright.dev/docs/input#dragging-manually
		await subpageEl.hover()
		await subpageEl.hover()
		// Wait for the hovered page to be armed as nest-into target
		await expect(subpageEl).toHaveClass(/highlight-target/)
		await page.mouse.up()

		await pageList.expectPageListOrder([page3.data.title, subpage.data.title, page1.data.title, page2.data.title])
	})

	test('Move page into subpage of expanded page after hovering its parent', async ({ page, user, collective, pageList }) => {
		const subpage = await collective.createPage({ title: 'Subpage', parentId: page3.data.id, user, page })

		await collective.openCollective()
		await expect(pageList.el).toBeVisible()
		await pageList.toggleExpandPage(page3.data.title)

		const page1El = pageList.getPageItem(page1.data.title)
		const page3El = pageList.getPageItem(page3.data.title)
		const subpageEl = pageList.getPageItem(subpage.data.title)

		await page1El.hover()
		await page.mouse.down()
		// Rest on the expanded parent's own row (the item element spans the whole
		// subtree, so the default hover position would land on the subpage)
		await page3El.hover({ position: { x: 100, y: 22 } })
		await page3El.hover({ position: { x: 100, y: 22 } })
		// Wait for the hovered page to be armed as nest-into target
		await expect(page3El).toHaveClass(/highlight-target/)

		// Slide onto the subpage
		await subpageEl.hover()
		await subpageEl.hover()
		await expect(subpageEl).toHaveClass(/highlight-target/)
		await expect(page3El).not.toHaveClass(/highlight-target/)
		await page.mouse.up()

		await pageList.expectPageListOrder([page3.data.title, subpage.data.title, page1.data.title, page2.data.title])
	})

	test('Drop page outside list reverts order', async ({ page, pageList, editor }) => {
		await page2.open(true)
		await expect(pageList.el).toBeVisible()
		await pageList.expectPageListOrder([page3.data.title, page2.data.title, page1.data.title])

		const page1El = pageList.getPageItem(page1.data.title)
		const page2El = pageList.getPageItem(page2.data.title)

		await page1El.hover()
		await page.mouse.down()
		await page2El.hover()
		await editor.menubar.hover()
		await page.mouse.up()
		await pageList.expectPageListOrder([page3.data.title, page2.data.title, page1.data.title])
	})

	test('Drag page into editor', async ({ pageList, editor }) => {
		await page2.open(true)
		await expect(pageList.el).toBeVisible()
		editor.setMode(true)
		await expect(editor.getContent()).toBeEmpty()

		await pageList.getPageItem(page1.data.title)
			.dragTo(editor.getContent())
		await editor.hasCollectiveLink(page1.data.title)
	})
})
