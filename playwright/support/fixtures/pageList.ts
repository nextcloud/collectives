/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { PageListSection } from '../sections/PageListSection.ts'

type PageListFixture = {
	pageList: PageListSection
}

export const test = baseTest.extend<PageListFixture>({
	pageList: async ({ page }, use) => {
		const pageList = new PageListSection(page)
		await use(pageList)
	},
})
