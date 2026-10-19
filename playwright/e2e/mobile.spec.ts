/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'
import { test as titleBarTest } from '../support/fixtures/titleBar.ts'
import { randomString } from '../support/helpers/randomString.ts'

const test = mergeTests(createCollectiveTest, navigationTest, pageListTest, titleBarTest).extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: randomString(), pages: [{ title: 'Page 1' }] },
		{ name: randomString() },
	]),
})

test.describe('Collective on mobile', () => {
	// The closed navigation is moved out of the viewport rather than hidden, hence `toBeInViewport()`.
	test.use({ viewport: { width: 360, height: 800 } })

	test('Opens collectives navigation', async ({ collectives, navigation, pageList }) => {
		await collectives[0].openApp()
		await expect(pageList.el).not.toBeInViewport()

		await navigation.open()
		await expect(pageList.el).toBeInViewport()

		await navigation.openCollectiveSelector()
		for (const collective of collectives) {
			await expect(navigation.getCollectiveItem(collective.data.name)).toBeVisible()
		}
	})

	test('Allows to navigate pages', async ({ collective, navigation, pageList, titleBar }) => {
		await collective.openCollective()
		await expect(pageList.el).not.toBeInViewport()

		await navigation.open()
		await expect(pageList.el).toBeInViewport()

		await pageList.getPageItem('Page 1').getByRole('link', { name: 'Page 1' }).click()
		await expect(pageList.el).not.toBeInViewport()
		await expect(titleBar.title).toHaveValue('Page 1')
	})
})
