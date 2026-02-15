/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as filesAppTest } from '../support/fixtures/filesApp.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const collectiveName1 = 'Test Collective 1'
const collectiveName2 = 'Test Collective 2'

const collectivesTest = createCollectivesTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: collectiveName1 },
		{ name: collectiveName2 },
	]),
})

const test = mergeTests(collectivesTest, filesAppTest, navigationTest)

test.describe('Settings', () => {
	test.beforeEach(async ({ collective }) => {
		await collective.openApp()
	})

	test('Can change collectives folder', async ({ getFileListEntry, navigation, openFilesApp, openFile }) => {
		const randomFolder = Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)
		await navigation.setUserFolder(randomFolder)
		await navigation.openCollectivesSettings()

		// Input field has new value after setting it
		await expect(navigation.collectivesFolderInputEl).toHaveValue(`/${randomFolder}`)

		await openFilesApp()
		await openFile(randomFolder)
		await expect(getFileListEntry(collectiveName1)).toBeVisible()
		await expect(getFileListEntry(collectiveName2)).toBeVisible()
	})
})
