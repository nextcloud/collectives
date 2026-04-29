/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'
import { test as titleBarTest } from '../support/fixtures/titleBar.ts'

const test = mergeTests(createCollectiveTest, pageListTest, titleBarTest)

test.describe('Page tags management', () => {
	let taggedPage: CollectivePage

	test.beforeEach(async ({ page, user, collective }) => {
		taggedPage = await collective.createPage({ title: 'Tagged Page', user, page })
		await taggedPage.open(true)
	})

	test('Allows to manage tags', async ({ page, titleBar }) => {
		await titleBar.clickActionMenu('Manage tags')

		// Create and add tag
		await page.getByLabel('Search or create tag').fill('initial')
		await page.getByRole('button', { name: 'Create new tag' }).click()
		await expect(page.locator('.toast-success').getByText('Created tag initial')).toBeVisible()
		await expect(page.locator('.toast-success').getByText('Added tag initial to page')).toBeVisible()
		await expect(page.locator('.page-tags-container .tag')).toContainText('initial')

		// Choose tag color
		const initialTagRow = page.locator('.tags-modal__tag').filter({ hasText: 'initial' })
		await initialTagRow.getByRole('button', { name: 'Change tag color' }).click()
		const colorPicker = page.locator('.color-picker')
		await colorPicker.locator('.color-picker__simple-color-circle').first().click()
		await colorPicker.getByRole('button', { name: 'Choose' }).click()
		await expect(page.locator('.toast-success').getByText('Updated tag initial')).toBeVisible()

		// Rename tag
		await initialTagRow.getByRole('button', { name: 'Actions' }).click()
		await page.getByRole('menuitem', { name: 'Rename' }).click()
		await page.getByRole('textbox', { name: 'Tag name' }).fill('testing')
		await page.getByRole('textbox', { name: 'Tag name' }).press('Enter')
		await expect(page.locator('.toast-success').getByText('Updated tag testing')).toBeVisible()
		await expect(page.locator('.page-tags-container .tag')).toContainText('testing')

		// Mark tag as deleted, then close modal to really delete
		const testingTagRow = page.locator('.tags-modal__tag').filter({ hasText: 'testing' })
		await testingTagRow.getByRole('button', { name: 'Actions' }).click()
		await page.getByRole('menuitem', { name: 'Delete' }).click()
		await page.getByRole('dialog', { name: 'Manage Tags' })
			.getByRole('button', { name: 'Close' }).click()
		await expect(page.locator('.toast-success').getByText('Deleted 1 tag')).toBeVisible()
		await expect(page.locator('.page-tags-container .tag')).toHaveCount(0)
	})

	test('Allows to view extra tags in page', async ({ page, titleBar }) => {
		await titleBar.clickActionMenu('Manage tags')

		// Create and add 7 tags via UI
		const tags = Array.from({ length: 7 }, (_, i) => `tag${i + 1}`)
		for (const tagName of tags) {
			await page.getByLabel('Search or create tag').fill(tagName)
			await page.getByRole('button', { name: 'Create new tag' }).click()
			await expect(page.getByLabel('Search or create Tag')).toHaveValue('')
		}
		await page.getByRole('dialog', { name: 'Manage Tags' })
			.getByRole('button', { name: 'Close' }).click()

		// First 5 tags + overflow pill visible
		await expect(page.locator('.page-tags-container .tag')).toHaveCount(6)
		await expect(page.locator('.page-tags-container .tag')).toContainText(tags.slice(0, 5))

		// Overflow pill
		const overflowPill = page.locator('.page-tags-container .tag.tag-invisible')
		await expect(overflowPill).toBeVisible()
		await overflowPill.click()
		await expect(page.locator('.page-tags.popover .tag')).toContainText(tags.slice(5))
	})
})

test.describe('Page tags in page filter', () => {
	let taggedPage: CollectivePage
	let untaggedPage: CollectivePage

	test.beforeEach(async ({ page, user, collective }) => {
		taggedPage = await collective.createPage({ title: 'Tagged Page', user, page })
		untaggedPage = await collective.createPage({ title: 'Untagged Page', user, page })

		const tags = Array.from({ length: 7 }, (_, i) => `tag${i + 1}`)
		for (const tagName of tags) {
			const tag = await collective.createTag({ name: tagName, page })
			await collective.addTagToPage({ pageId: taggedPage.data.id, tagId: tag.id, page })
		}
		await taggedPage.open(true)
	})

	test('Allows to filter page list by clicking on tag in titlebar', async ({ page, pageList }) => {
		await page.locator('.page-tags-container .tag.tag-invisible').click()
		await page.locator('.page-tags.popover .tag').filter({ hasText: 'tag6' }).click()

		await expect(pageList.activeFilterTags).toContainText('tag6')

		// Only pages with tag listed in page list
		await expect(pageList.getPageItem(taggedPage.data.title)).toBeVisible()
		await expect(pageList.getPageItem(untaggedPage.data.title)).not.toBeVisible()

		// Removing tag from filter
		await pageList.activeFilterTags.locator('.tag').filter({ hasText: 'tag6' })
			.getByRole('button', { name: 'Remove tag' }).click()
		await expect(pageList.activeFilterTags.locator('.tag')).toHaveCount(0)
		await expect(pageList.getPageItem(untaggedPage.data.title)).toBeVisible()
	})

	test('Allows to filter page list via dropdown in page filter', async ({ pageList }) => {
		// Type in filter - tag dropdown appears
		await pageList.filter.fill('tag')
		await expect(pageList.filterTagSelect.locator('.tag')).toContainText(['tag2', 'tag1'])

		// ESC closes the tag dropdown
		await pageList.filter.press('Escape')
		await expect(pageList.filterTagSelect).not.toBeVisible()

		// Type again and select tag2
		await pageList.filter.clear()
		await pageList.filter.fill('tag')
		await pageList.filterTagSelect.locator('.tag').filter({ hasText: 'tag2' }).click()

		// tag2 active
		await expect(pageList.activeFilterTags).toContainText('tag2')
		await expect(pageList.filter).toHaveValue('')

		// Only pages with tag listed in page list
		await expect(pageList.getPageItem(taggedPage.data.title)).toBeVisible()
		await expect(pageList.getPageItem(untaggedPage.data.title)).not.toBeVisible()

		// Type again - already filtered tag2 not in dropdown
		await pageList.filter.clear()
		await pageList.filter.fill('tag')
		await expect(pageList.filterTagSelect.locator('.tag').filter({ hasText: 'tag2' })).not.toBeVisible()

		// Select tag3
		await pageList.filterTagSelect.locator('.tag').filter({ hasText: 'tag3' }).click()

		// tag2+tag3 active
		await expect(pageList.activeFilterTags.locator('.tag')).toContainText(['tag2', 'tag3'])

		// Only pages with selected tag listed in page list
		await expect(pageList.getPageItem(taggedPage.data.title)).toBeVisible()
		await expect(pageList.getPageItem(untaggedPage.data.title)).not.toBeVisible()
	})
})
