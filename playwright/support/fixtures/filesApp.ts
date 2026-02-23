/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { FilesAppSection } from '../sections/FilesAppSection.ts'

type FilesAppFixture = {
	filesApp: FilesAppSection
	setShowHiddenFiles: (show: boolean) => Promise<void>
}

export const test = baseTest.extend<FilesAppFixture>({
	filesApp: async ({ page }, use) => {
		const filesApp = new FilesAppSection(page)
		await use(filesApp)
	},

	setShowHiddenFiles: ({ page }, use) => use(async (show: boolean) => {
		await page.request.put(
			'/index.php/apps/files/api/v1/config/show_hidden',
			{
				headers: {
					Accept: 'application/json',
					'Content-Type': 'application/json',
				},
				data: {
					value: show,
				},
				failOnStatusCode: true,
			},
		)
	}),
})
