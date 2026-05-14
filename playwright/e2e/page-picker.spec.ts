/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { randomString } from '../support/helpers/randomString.ts'

const test = mergeTests(createCollectiveTest, editorTest)

test.describe('Custom page picker - local search', () => {
	let targetPage: CollectivePage

	test.beforeEach(async ({ user, page, collective, editor }) => {
		const sourcePage = await collective.createPage({ title: 'Source page', user, page })
		targetPage = await collective.createPage({ title: 'Target page', user, page })
		await sourcePage.open(true)

		editor.setMode(true)
		await editor.clickMenu('Insert link', 'Link to page')
		await page.waitForTimeout(200)
	})

	test('link to local page via search', async ({ page, editor }) => {
		const targetPageItem = editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Target page' })
		// Should already be listed without searching
		await expect(targetPageItem).toBeVisible()

		await editor.pagePickerSearch.pressSequentially('Target page')

		// Still listed with searching
		await expect(targetPageItem).toBeVisible()

		await targetPageItem.click()

		const pageWidget = editor.getContent().locator('.widget-custom a.collective-page')
		await expect(pageWidget).toBeVisible()
		const origin = new URL(page.url()).origin
		await expect(pageWidget).toHaveAttribute('href', origin + targetPage.getPageUrl())
	})

	test('searching "landing page" lists landing pages', async ({ editor }) => {
		await editor.pagePickerSearch.pressSequentially('landing page')

		const landingPageItem = editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Landing page' })
		await expect(landingPageItem).toBeVisible()
	})

	test('searching collective title lists landing page', async ({ collective, editor }) => {
		await editor.pagePickerSearch.pressSequentially(collective.data.name)

		const landingPageItem = editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Landing page' })
		await expect(landingPageItem).toBeVisible()
	})
})

test.describe('Custom page picker - cross-collective search', () => {
	test.use({
		// eslint-disable-next-line no-empty-pattern
		collectiveConfigs: async ({}, use) => {
			await use([
				{ name: randomString() },
				{ name: randomString() },
			])
		},
	})

	let otherTargetPage: CollectivePage

	test.beforeEach(async ({ user, page, collectives, editor }) => {
		const sourcePage = await collectives[0].createPage({ title: 'Source page', user, page })
		otherTargetPage = await collectives[1].createPage({ title: 'Other collective page', user, page, content: 'content' })
		await sourcePage.open(true)

		editor.setMode(true)
		await editor.clickMenu('Insert link', 'Link to page')
		await page.waitForTimeout(200)
	})

	test('link to page from other collective', async ({ page, editor }) => {
		await editor.pagePicker.locator('.searchbar [aria-haspopup="menu"]').click()
		await page.getByText('Limit to current collective').click()

		// Should already be listed without searching
		const otherPageItem = editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Other collective page' })
		await expect(otherPageItem).toBeVisible()

		// Search for the page in the other collective
		await editor.pagePickerSearch.pressSequentially('Other collective page')

		// Still listed with searching
		await expect(otherPageItem).toBeVisible()

		await otherPageItem.click()

		const pageWidget = editor.getContent().locator('.widget-custom a.collective-page')
		await expect(pageWidget).toBeVisible()
		const origin = new URL(page.url()).origin
		await expect(pageWidget).toHaveAttribute('href', origin + otherTargetPage.getPageUrl())
	})

	test('searching "Landing page" lists landing page', async ({ page, editor }) => {
		await editor.pagePicker.locator('.searchbar [aria-haspopup="menu"]').click()
		await page.getByText('Limit to current collective').click()

		await editor.pagePickerSearch.fill('Landing page')
		await expect(editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Source page' })).not.toBeVisible()

		const landingPageItem = editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Landing page' })
		await expect(landingPageItem).toHaveCount(2)
	})

	test('searching collective title lists landing page', async ({ page, collectives, editor }) => {
		await editor.pagePicker.locator('.searchbar [aria-haspopup="menu"]').click()
		await page.getByText('Limit to current collective').click()

		await editor.pagePickerSearch.fill(collectives[1].data.name)
		await expect(editor.pagePicker.locator('.page-preview-item').filter({ hasText: 'Source page' })).not.toBeVisible()

		const landingPageItem = editor.pagePicker.locator('.page-preview-item')
			.filter({ hasText: 'Landing page' })
			.filter({ hasText: collectives[1].data.name })
		await expect(landingPageItem).toBeVisible()
	})
})
