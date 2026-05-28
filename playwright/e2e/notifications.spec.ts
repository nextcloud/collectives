/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { User } from '../support/fixtures/User.ts'
import { randomString } from '../support/helpers/randomString.ts'
import { apiUrl, ocsHeaders } from '../support/helpers/urls.ts'

type MemberFixture = { page: Page, user: User }

const test = createCollectiveTest.extend<{ member: MemberFixture }>({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: randomString(), pages: [{ title: 'Notified Page' }] },
	]),
	member: async ({ collective, browser, baseURL }, use) => {
		const account: Account = await collective.addMember()
		const memberPage = await loginAsUser(browser, baseURL, account)
		await use({ page: memberPage, user: new User(account) })
		await memberPage.close()
	},
})

async function expectNotification(page: Page, collectiveName: string, pageTitle: string): Promise<void> {
	await page.locator('.notifications-button').click()
	const notificationBox = page.locator('#header-menu-notifications')
	await expect(notificationBox).toBeVisible()
	const notification = notificationBox.locator('.notification').first()
	await expect(notification).toBeVisible()
	await expect(notification).toContainText(collectiveName)
	await expect(notification).toContainText(pageTitle)
}

test.describe('Notifications', () => {
	test.beforeEach(async ({ collective }) => {
		await collective.notify()
	})

	test('User receives notification when member touches a page', async ({ collective, page, member }) => {
		const testPage = collective.getPageByTitle('Notified Page')
		await member.page.request.get(
			apiUrl('v1.0', 'collectives', collective.data.id, 'pages', testPage.data.id, 'touch'),
			{ headers: ocsHeaders, failOnStatusCode: true },
		)

		await collective.openCollective()
		await expectNotification(page, collective.data.name, testPage.data.title)
	})

	test('User receives notification when member updates page content', async ({ collective, page, member }) => {
		const testPage = collective.getPageByTitle('Notified Page')
		await testPage.setContent({
			content: '# Updated by member',
			user: member.user,
			page: member.page,
		})

		await collective.openCollective()
		await expectNotification(page, collective.data.name, testPage.data.title)
	})
})
