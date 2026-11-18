/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as offlineTest } from '../support/fixtures/offline.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'
import { test as pageSidebarTest } from '../support/fixtures/pageSidebar.ts'

const test = mergeTests(createCollectivesTest, offlineTest, pageListTest, pageSidebarTest)

// As we switch on and off the network
// we cannot run tests in parallel.
test.describe.configure({ mode: 'serial' })

test.describe('Offline mode', () => {
	test.use({})

	test.beforeEach(async ({ collective }) => {
		await collective.openCollective()
	})

	test('Shows offline indicator', async ({ collective, setOffline }) => {
		await expect(collective.page.locator('.offline-indicator')).not.toBeVisible()
		await setOffline()
		await expect(collective.page.locator('.offline-indicator')).toBeVisible()
	})

	test('Shows offline state in versions tab', async ({ pageSidebar, setOnline, setOffline }) => {
		const versionsTab = await pageSidebar.openSidebarTab('Versions')
		await expect(pageSidebar.getVersionListItem('Current version')).toBeVisible()

		await setOffline()
		await expect(pageSidebar.getVersionListItem('Current version')).not.toBeVisible()
		await expect(versionsTab).toContainText('Offline')
		await setOnline()
	})

	test('Shows offline state for page content and loads it when back online', async ({ page, user, collective, pageList, setOffline, setOnline }) => {
		await collective.createPage({ title: 'Offline page', content: 'Some content', user, page })
		// Open the collective again, so that the page list contains the new page
		await collective.openCollective()

		await setOffline()
		await pageList.openPage('Offline page')
		await expect(page.locator('.collectives-text-container')).toContainText('Offline')
		await expect(collective.getReaderContent()).toBeHidden()

		await setOnline()
		await expect(collective.getReaderContent()).toContainText('Some content')
	})
})
