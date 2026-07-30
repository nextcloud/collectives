/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { notifyLevels } from '../../src/constants.js'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { User } from '../support/fixtures/User.ts'
import { randomString } from '../support/helpers/randomString.ts'
import { apiUrl, ocsHeaders } from '../support/helpers/urls.ts'
import { EditorSection } from '../support/sections/EditorSection.ts'

type MemberFixture = { page: Page, user: User }

const mergedTest = mergeTests(createCollectiveTest, navigationTest)

const test = mergedTest.extend<{ member: MemberFixture }>({
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

async function expectNoNotification(page: Page): Promise<void> {
	await page.locator('.notifications-button').click()
	const notificationBox = page.locator('#header-menu-notifications')
	await expect(notificationBox).toBeVisible()
	const notification = notificationBox.locator('.notification')
	await expect(notification).toHaveCount(0)
}

test.describe('Notifications', () => {
	test('User receives notification on being mentioned by default', async ({ collective, page, member, user }) => {
		const testPage = collective.getPageByTitle('Notified Page')

		await member.page.goto(testPage.getPageUrl())
		const memberEditor = new EditorSection(member.page)
		await memberEditor.switchMode(true)
		await memberEditor.getContent().pressSequentially(`Mentioning @${user.account.userId}`)
		const suggestion = memberEditor.getMentionSuggestions().getByText(user.account.userId, { exact: true })
		await suggestion.click()
		await memberEditor.save()

		await collective.openCollective()
		await expectNotification(page, collective.data.name, testPage.data.title)
	})

	test('User does not receive notification when notify level is off', async ({ collective, page, member, user }) => {
		await collective.setNotify(notifyLevels.NOTIFY_OFF)

		const testPage = collective.getPageByTitle('Notified Page')

		await member.page.goto(testPage.getPageUrl())
		const memberEditor = new EditorSection(member.page)
		await memberEditor.switchMode(true)
		await memberEditor.getContent().pressSequentially(`Mentioning @${user.account.userId}`)
		const suggestion = memberEditor.getMentionSuggestions().getByText(user.account.userId, { exact: true })
		await suggestion.click()
		await memberEditor.save()

		await collective.openCollective()
		await expectNoNotification(page)
	})

	test('User receives notification when activated and member updates page content', async ({ collective, page, member }) => {
		await collective.setNotify(notifyLevels.NOTIFY_ALL)

		const testPage = collective.getPageByTitle('Notified Page')
		await testPage.setContent({
			content: '# Updated by member',
			user: member.user,
			page: member.page,
		})

		await collective.openCollective()
		await expectNotification(page, collective.data.name, testPage.data.title)
	})

	test('User sets notify via UI and receives notification when member touches a page', async ({ collective, page, member, navigation }) => {
		await collective.openCollective()
		await navigation.open()
		await navigation.clickCollectiveMenu(collective.data.name, 'Notifications')
		await page.getByRole('button', { name: 'All changes', exact: true }).click()

		const testPage = collective.getPageByTitle('Notified Page')
		await member.page.request.get(
			apiUrl('v1.0', 'collectives', collective.data.id, 'pages', testPage.data.id, 'touch'),
			{ headers: ocsHeaders, failOnStatusCode: true },
		)

		await expectNotification(page, collective.data.name, testPage.data.title)
	})
})
