/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { NavigationSection } from '../sections/NavigationSection.ts'

type NavigationFixture = {
	navigation: NavigationSection
}

export const test = baseTest.extend<NavigationFixture>({
	navigation: async ({ page }, use) => {
		const navigation = new NavigationSection(page)
		await use(navigation)
	},
})
