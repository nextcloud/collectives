/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as filesAppTest } from '../support/fixtures/filesApp.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { randomString } from '../support/helpers/randomString.ts'

const collectivesTest = createCollectivesTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: randomString() },
		{ name: randomString() },
	]),
})

const test = mergeTests(collectivesTest, filesAppTest, navigationTest)

test.describe('Settings', () => {
	test.beforeEach(async ({ collective }) => {
		await collective.openApp()
	})

	test('Can change collectives folder', async ({ collectives, navigation, filesApp }) => {
		const randomFolder = Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)
		await navigation.setUserFolder(randomFolder)
		await navigation.openCollectivesSettings()

		// Input field has new value after setting it
		await expect(navigation.collectivesFolderInputEl).toHaveValue(`/${randomFolder}`)

		await filesApp.open()
		await filesApp.openFile(randomFolder)
		await expect(filesApp.getFileListEntry(collectives[0].data.name)).toBeVisible()
		await expect(filesApp.getFileListEntry(collectives[1].data.name)).toBeVisible()
	})
})
