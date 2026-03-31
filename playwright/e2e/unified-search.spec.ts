/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator } from '@playwright/test'
import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { runOcc } from '@nextcloud/e2e-test-server'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'

const test = mergeTests(createCollectiveTest, editorTest)

test.describe('Unified search', () => {
	let page1: CollectivePage
	let page2: CollectivePage
	let unifiedSearchDialog: Locator

	test.beforeEach(async ({ page, user, collective }) => {
		page1 = await collective.createPage({ title: 'Page 1', user, page })
		page2 = await collective.createPage({ title: 'Page 2', user, page })
		await collective.openCollective()
		await page.getByRole('button', { name: 'Unified search' }).click()
		unifiedSearchDialog = page.locator('.unified-search-modal')
		await expect(unifiedSearchDialog).toBeVisible()
	})

	// eslint-disable-next-line no-empty-pattern
	test('Search for page title', async ({}) => {
		await unifiedSearchDialog.getByRole('textbox').fill('page')
		await expect(unifiedSearchDialog.getByRole('heading', { name: 'Collectives - Pages' })
			.locator('~ ul')
			.locator('.list-item-content__name'))
			.toContainText(['Landing page', page1.data.title, page2.data.title])
	})

	test('Search for page content', async ({ page, user, collective }) => {
		await page1.setContent({ content: 'Lorem ipsum dolor sit amet consectetur adipiscing elit.', user, page })
		await page2.setContent({ content: 'Lorem ipsum dolor sit amet consectetur adipiscing elit.', user, page })
		await runOcc([
			'collectives:index',
			collective.data.name,
		])
		await unifiedSearchDialog.getByRole('textbox').fill('lorem')
		await expect(unifiedSearchDialog.getByRole('heading', { name: 'Collectives - Page content' })
			.locator('~ ul')
			.locator('.list-item-content__name'))
			.toContainText([/^Lorem ipsum.*/, /^Lorem ipsum.*/])
	})
})
