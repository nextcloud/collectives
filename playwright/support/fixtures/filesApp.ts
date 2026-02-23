/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { FilesAppSection } from '../sections/FilesAppSection.ts'

type FilesAppFixture = {
	filesApp: FilesAppSection
}

export const test = baseTest.extend<FilesAppFixture>({
	filesApp: async ({ page }, use) => {
		const filesApp = new FilesAppSection(page)
		await use(filesApp)
	},
})
