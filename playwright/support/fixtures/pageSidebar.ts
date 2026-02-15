/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { PageSidebarSection } from '../sections/PageSidebarSection.ts'

type PageSidebarFixtures = {
	pageSidebar: PageSidebarSection
}

export const test = baseTest.extend<PageSidebarFixtures>({
	pageSidebar: async ({ page }, use) => {
		const pageSidebar = new PageSidebarSection(page)
		await use(pageSidebar)
	},
})
