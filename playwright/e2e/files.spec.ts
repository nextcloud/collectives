/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as filesAppTest } from '../support/fixtures/filesApp.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'

const test = mergeTests(createCollectivesTest, filesAppTest, navigationTest)

test.describe('Files app', () => {
	test('Collectives folder is visible in files app', async ({ collective, filesApp, setShowHiddenFiles }) => {
		await collective.openCollective()

		await setShowHiddenFiles(true)
		await filesApp.open()

		await expect(filesApp.getFileListEntry('.Collectives')).toBeVisible()
		await filesApp.openFile('.Collectives')
		await expect(filesApp.getFileListEntry('Test Collective 1')).toBeVisible()
		await filesApp.hasCollectivesHeader()
		await filesApp.openFile('Test Collective 1')
		await expect(filesApp.getFileListEntry('Readme.md')).toBeVisible()
		await filesApp.hasCollectivesHeader()
	})
})
