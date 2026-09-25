/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'
import type { Team } from '../support/fixtures/Team.ts'

import { createRandomUser } from '@nextcloud/e2e-test-server/playwright'
import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as createTeamsTest } from '../support/fixtures/create-teams.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { createTeam } from '../support/fixtures/Team.ts'
import { randomString } from '../support/helpers/randomString.ts'
import { NavigationSection } from '../support/sections/NavigationSection.ts'

type OtherUser = { account: Account, page: Page }

const NAME_TAKEN_ERROR = 'A collective/team with this name already exists'

const mergedTest = mergeTests(createCollectivesTest, createTeamsTest, navigationTest)

const test = mergedTest.extend<{ otherUser: OtherUser, foreignTeam: Team }>({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: `Preexisting Collective ${randomString()}` },
	]),
	// eslint-disable-next-line no-empty-pattern
	teamConfigs: async ({}, use) => use([
		{ name: `Preexisting Team ${randomString()}` },
		{ name: `History Club ${randomString()}`, visible: true, open: true },
	]),
	otherUser: async ({ browser, baseURL }, use) => {
		const account = await createRandomUser()
		const otherPage = await loginAsUser(browser, baseURL, account)
		await use({ account, page: otherPage })
		await otherPage.close()
	},
	// A visible and open team of another user, that our user is not a member of
	foreignTeam: async ({ otherUser }, use) => {
		const team = await createTeam({
			name: `Foreign Team ${randomString()}`,
			visible: true,
			open: true,
			page: otherUser.page,
		})
		await use(team)
		await team.delete()
	},
})

test.describe('Collective name conflicts', () => {
	test('reports existing team', async ({ navigation, foreignTeam, page }) => {
		await page.goto('/index.php/apps/collectives')
		await navigation.open()
		await navigation.createCollective(foreignTeam.data.sanitizedName)

		await expect(navigation.newCollectiveNameError).toContainText(NAME_TAKEN_ERROR)
	})

	test('reports existing collective', async ({ collective, navigation }) => {
		await collective.openApp()
		await navigation.open()
		await navigation.createCollective(collective.data.name)

		await expect(navigation.newCollectiveNameError).toContainText(NAME_TAKEN_ERROR)
	})

	test('creates collectives by picking team', async ({ navigation, page, teams }) => {
		const teamName = teams[1].data.sanitizedName

		await page.goto('/index.php/apps/collectives')
		await navigation.open()
		await navigation.createCollectiveForTeam(teamName)

		await expect(page.locator('[data-cy-collectives="page-title-container"] input'))
			.toHaveValue(teamName)
		await expect(page.locator('.toast-info'))
			.toContainText(`Created collective "${teamName}" for existing team.`)
	})

	test('creates collectives for admins of corresponding team', async ({ navigation, page, teams }) => {
		const teamName = teams[0].data.sanitizedName

		await page.goto('/index.php/apps/collectives')
		await navigation.open()
		await navigation.createCollective(teamName)

		await expect(page.locator('[data-cy-collectives="page-title-container"] input'))
			.toHaveValue(teamName)
		await expect(page.locator('.toast-info'))
			.toContainText(`Created collective "${teamName}" for existing team.`)
	})

	test('collectives of visible teams only show for members', async ({ collective, otherUser, page, teams, user }) => {
		const teamName = teams[1].data.sanitizedName

		// The other user is a member of the collective, but not of the visible team
		await collective.addMember(otherUser.account)
		await user.createCollective({ name: teamName }, page)

		await otherUser.page.goto('/index.php/apps/collectives')
		const otherNavigation = new NavigationSection(otherUser.page)
		await otherNavigation.open()
		await otherNavigation.openCollectiveSelector()

		await expect(otherNavigation.getCollectiveItem(collective.data.name)).toBeVisible()
		await expect(otherNavigation.getCollectiveItem(teamName)).toHaveCount(0)
	})
})
