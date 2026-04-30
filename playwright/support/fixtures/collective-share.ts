/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { test as baseTest } from '@playwright/test'
import { EditorSection } from '../sections/EditorSection.ts'
import { PageListSection } from '../sections/PageListSection.ts'
import { TitleBarSection } from '../sections/TitleBarSection.ts'

interface CollectiveShareFixtures {
	sharePage: Page
	shareEditor: EditorSection
	sharePageList: PageListSection
	shareTitleBar: TitleBarSection
}

export const test = baseTest.extend<CollectiveShareFixtures>({
	sharePage: async ({ browser, baseURL }, use) => {
		const sharePage = await browser.newPage({ baseURL })
		await use(sharePage)
		await sharePage.close()
	},
	shareEditor: async ({ sharePage }, use) => {
		await use(new EditorSection(sharePage))
	},
	sharePageList: async ({ sharePage }, use) => {
		await use(new PageListSection(sharePage))
	},
	shareTitleBar: async ({ sharePage }, use) => {
		await use(new TitleBarSection(sharePage))
	},
})
