/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Browser, Page } from '@playwright/test'

import { createRandomUser, login } from '@nextcloud/e2e-test-server/playwright'
import { test as base } from '@playwright/test'
import { User } from './User.ts'

export interface UserFixture {
	account: Account
	user: User
}

/**
 * Log in as any account in a new browser page and return it.
 *
 * @param browser - the browser object
 * @param baseURL - the base URL
 * @param account - the user account
 */
export async function loginAsUser(browser: Browser, baseURL: string | undefined, account: Account): Promise<Page> {
	// Important: make sure we authenticate in a clean environment by unsetting storage state.
	const page = await browser.newPage({ storageState: undefined, baseURL })
	await authenticate(page, account)
	return page
}

/**
 * Log in as any account in an existing browser page.
 *
 * @param page - the browser page
 * @param account - the user account
 */
async function authenticate(page: Page, account: Account): Promise<void> {
	await login(page.request, account)
	const tokenResponse = await page.request.get('./csrftoken', { failOnStatusCode: true })
	const { token } = (await tokenResponse.json()) as { token: string }
	await page.context().setExtraHTTPHeaders({ requesttoken: token })
}

/**
 * This test fixture ensures a new random user is created and used for the test (current page)
 */
export const test = base.extend<object, UserFixture>({
	// eslint-disable-next-line no-empty-pattern
	account: [async ({}, use) => {
		const account = await createRandomUser()
		await use(account)
	}, { scope: 'worker' }],
	// Builds on the built-int `page` fixture, so options from `test.use()` apply
	page: async ({ account, page }, use) => {
		await authenticate(page, account)
		await use(page)
	},
	user: [async ({ account }, use) => {
		const user = new User(account)
		await use(user)
	}, { scope: 'worker' }],
})
