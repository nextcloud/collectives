/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { TitleBarSection } from '../sections/TitleBarSection.ts'

type TitleBarFixtures = {
	titleBar: TitleBarSection
}

export const test = baseTest.extend<TitleBarFixtures>({
	titleBar: async ({ page }, use) => {
		const titleBar = new TitleBarSection(page)
		await use(titleBar)
	},
})
