/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createRandomUser, login } from '@nextcloud/e2e-test-server/playwright'
import { test as base } from '@playwright/test'
import { User } from './User.ts'

type RandomUser = Awaited<ReturnType<typeof createRandomUser>>

export interface UserFixture {
	randomUser: RandomUser
	user: User
}

/**
 * This test fixture ensures a new random user is created and used for the test (current page)
 */
export const test = base.extend<UserFixture>({
	// eslint-disable-next-line no-empty-pattern
	randomUser: async ({}, use) => {
		const randomUser = await createRandomUser()
		await use(randomUser)
	},
	page: async ({ browser, baseURL, randomUser }, use) => {
		// Important: make sure we authenticate in a clean environment by unsetting storage state.
		const page = await browser.newPage({
			storageState: undefined,
			baseURL,
		})

		await login(page.request, randomUser)

		await use(page)
		await page.close()
	},
	user: async ({ page, randomUser }, use) => {
		const user = new User(page, randomUser.userId)
		await use(user)
	},
})
