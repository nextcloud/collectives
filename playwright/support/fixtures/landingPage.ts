/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { test as baseTest } from '@playwright/test'
import { LandingPageSection } from '../sections/LandingPageSection.ts'

type LandingPageFixture = {
	landingPage: LandingPageSection
}

export const test = baseTest.extend<LandingPageFixture>({
	landingPage: async ({ page }, use) => {
		const landingPage = new LandingPageSection(page)
		await use(landingPage)
	},
})
