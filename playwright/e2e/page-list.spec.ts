/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'

const test = mergeTests(createCollectiveTest, editorTest, pageListTest)

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
