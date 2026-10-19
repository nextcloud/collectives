/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { test as landingPageTest } from '../support/fixtures/landingPage.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { test as titleBarTest } from '../support/fixtures/titleBar.ts'
import { randomString } from '../support/helpers/randomString.ts'
import { LandingPageSection } from '../support/sections/LandingPageSection.ts'

type MemberFixture = {
	member: Account
	memberPage: Page
}

const test = mergeTests(createCollectiveTest, editorTest, landingPageTest, titleBarTest).extend<MemberFixture>({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: randomString(), pages: [{ title: 'Page 1' }, { title: 'Page 2' }, { title: 'Page 3' }] },
	]),
	member: async ({ collective }, use) => {
		await use(await collective.addMember())
	},
	memberPage: async ({ browser, baseURL, member }, use) => {
		const memberPage = await loginAsUser(browser, baseURL, member)
		await use(memberPage)
		await memberPage.close()
	},
})

/**
 * Generate markdown content that is longer than the viewport.
 */
function generateLongContent(): string {
	const paragraph = 'Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.'
	return Array.from({ length: 10 }, (_, i) => `## Paragraph ${i + 1}\n\n${paragraph}\n\n`).join('')
}

test.describe('Landing page', () => {
	test('Allows to toggle recent pages widget', async ({ page, collective, landingPage }) => {
		await collective.openCollective()
		await expect(landingPage.getRecentPageTile('Page 3')).toBeVisible()

		await landingPage.toggleRecentPages()
		await expect(landingPage.getRecentPageTile('Page 3')).not.toBeVisible()

		await page.reload()
		await collective.waitForReaderContent()
		await expect(landingPage.getRecentPageTile('Page 3')).not.toBeVisible()

		await landingPage.toggleRecentPages()
		await expect(landingPage.getRecentPageTile('Page 3')).toBeVisible()
	})

	test('Allows to open page from recent pages widget', async ({ collective, landingPage, titleBar }) => {
		await collective.openCollective()
		await landingPage.getRecentPageTile('Page 3').click()

		const page3 = collective.getPageByTitle('Page 3')
		await expect(collective.page).toHaveURL((url) => url.pathname.endsWith(page3.getPageUrl()))
		await expect(titleBar.title).toHaveValue('Page 3')
	})

	test('Allows to toggle members widget', async ({ page, collective, landingPage, member }) => {
		await collective.openCollective()
		await expect(landingPage.getMemberAvatar(member.userId)).toBeVisible()

		await landingPage.toggleMembers()
		await expect(landingPage.getMemberAvatar(member.userId)).not.toBeVisible()

		await page.reload()
		await collective.waitForReaderContent()
		await expect(landingPage.getMemberAvatar(member.userId)).not.toBeVisible()

		await landingPage.toggleMembers()
		await expect(landingPage.getMemberAvatar(member.userId)).toBeVisible()
	})

	test('Allows to open members modal as admin', async ({ page, collective, landingPage, member }) => {
		await collective.openCollective()
		await landingPage.getMembersButton(true).click()

		const memberRow = page.getByRole('dialog').locator('.current-members .member-row')
			.filter({ hasText: member.userId })
		await expect(memberRow.locator('.member-row__actions')).toBeVisible()
	})

	test('Allows to open members modal as member', async ({ collective, memberPage, user }) => {
		const landingPage = new LandingPageSection(memberPage)
		await memberPage.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)
		await expect(landingPage.getMemberAvatar(user.account.userId)).toBeVisible()
		await landingPage.getMembersButton(false).click()

		const adminRow = memberPage.getByRole('dialog').locator('.current-members .member-row')
			.filter({ hasText: user.account.userId })
		await expect(adminRow).toBeVisible()
		await expect(adminRow.locator('.member-row__actions')).toHaveCount(0)
	})

	// Failures might stem from changes in the text app
	test('Scrolling down hides the widgets', async ({ page, user, collective, editor, landingPage }) => {
		const rootPage = collective.getRootPage()
		await rootPage.setContent({ content: generateLongContent(), user, page })
		await collective.openCollective()

		const lastHeading = editor.getContent().getByRole('heading', { name: 'Paragraph 10' })
		await expect(landingPage.el).toBeInViewport()
		await expect(lastHeading).not.toBeInViewport()

		await lastHeading.scrollIntoViewIfNeeded()

		await expect(landingPage.el).not.toBeInViewport()
		await expect(editor.readerInfobar).toBeInViewport()
		await expect(lastHeading).toBeInViewport()
	})
})
