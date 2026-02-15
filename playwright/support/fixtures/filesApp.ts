/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Locator } from '@playwright/test'
import { test as baseTest } from '@playwright/test'

type FilesAppFixture = {
	openFilesApp: () => Promise<void>
	getFileListEntry: (fileName: string) => Locator
	openFile: (fileName: string) => Promise<void>
}

export const test = baseTest.extend<FilesAppFixture>({
	openFilesApp: ({ page }, use) => use(async () => {
		await page.goto('/apps/files/')
	}),
	getFileListEntry: ({ page }, use) => use((fileName: string) => {
		return page.locator(`[data-cy-files-list-row-name="${fileName}"]`)
	}),
	openFile: ({ page }, use) => use(async (fileName: string) => {
		// Open the file by clicking on it in the file list
		const fileEntry = page.locator(`[data-cy-files-list-row-name="${fileName}"]`)
		await fileEntry.click()
	}),
})
