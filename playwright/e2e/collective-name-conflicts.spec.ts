/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { createRandomUser } from '@nextcloud/e2e-test-server/playwright'
import { expect, mergeTests } from '@playwright/test'
import { createCollective } from '../support/fixtures/Collective.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { test as titleBarTest } from '../support/fixtures/titleBar.ts'
import { randomString } from '../support/helpers/randomString.ts'
import { createTeam, deleteCollectiveOrTeam } from '../support/helpers/teams.ts'
import { getToast } from '../support/helpers/toasts.ts'
import { NavigationSection } from '../support/sections/NavigationSection.ts'

/**
 * A logged in user whose teams and collectives get deleted after the test
 */
type TeamUser = {
	page: Page
	createTeam: (options: { name: string, visible?: boolean, open?: boolean }) => Promise<void>
	createCollective: (name: string) => Promise<void>
}

async function useTeamUser(page: Page, use: (teamUser: TeamUser) => Promise<void>) {
	const names: string[] = []
	await use({
		page,
		createTeam: async (options) => {
			names.push(options.name)
			await createTeam({ ...options, page })
		},
		createCollective: async (name) => {
			names.push(name)
			await createCollective({ name, page })
		},
	})
	for (const name of names) {
		await deleteCollectiveOrTeam({ name, page })
	}
}

const test = mergeTests(createCollectiveTest, navigationTest, titleBarTest).extend<{
	owner: TeamUser
	otherUser: TeamUser
}>({
	owner: async ({ page }, use) => {
		await useTeamUser(page, use)
	},
	otherUser: async ({ browser, baseURL }, use) => {
		const otherPage = await loginAsUser(browser, baseURL, await createRandomUser())
		await useTeamUser(otherPage, use)
		await otherPage.close()
	},
})

const nameExistsError = 'A collective/team with this name already exists'

test.describe('Collective name conflicts', () => {
	test('Reports existing team', async ({ page, navigation, otherUser }) => {
		const name = `Foreign Team ${randomString()}`
		await otherUser.createTeam({ name, visible: true, open: true })

		await page.goto('/index.php/apps/collectives')
		await navigation.createCollective(name)
		await expect(page.getByRole('dialog', { name: 'New collective' })
			.getByText(nameExistsError)).toBeVisible()
	})

	test('Reports existing collective', async ({ page, collective, navigation }) => {
		await page.goto('/index.php/apps/collectives')
		await navigation.createCollective(collective.data.name)
		await expect(page.getByRole('dialog', { name: 'New collective' })
			.getByText(nameExistsError)).toBeVisible()
	})

	test('Creates collective by picking team', async ({ page, navigation, owner, titleBar }) => {
		const name = `History Club ${randomString()}`
		await owner.createTeam({ name, visible: true, open: true })

		await page.goto('/index.php/apps/collectives')
		await navigation.openNewCollectiveDialog()
		await page.getByRole('button', { name: 'Select an existing team' }).click()
		await page.locator('.circle-selector').click()
		await page.getByRole('option', { name }).click()
		await page.getByRole('button', { name: 'Add members' }).click()
		await page.getByRole('button', { name: 'Create without members' }).click()

		await expect(titleBar.title).toHaveValue(name)
		await expect(getToast(page, 'success', `Created collective "${name}" for existing team.`)).toBeVisible()
	})

	test('Collectives of visible teams only show for members', async ({ owner, otherUser }) => {
		const name = `History Club ${randomString()}`
		await owner.createTeam({ name, visible: true, open: true })
		await owner.createCollective(name)
		// Own collective of the other user to know when the list got loaded
		const otherUserCollective = `Other Collective ${randomString()}`
		await otherUser.createCollective(otherUserCollective)

		const otherNavigation = new NavigationSection(otherUser.page)
		await otherUser.page.goto('/index.php/apps/collectives')
		await otherNavigation.openCollectiveSelector()
		await expect(otherNavigation.getCollectiveItem(otherUserCollective)).toBeVisible()
		await expect(otherNavigation.getCollectiveItem(name)).toHaveCount(0)
	})

	test('Creates collective for admin of corresponding team', async ({ page, navigation, owner, titleBar }) => {
		const name = `Preexisting Team ${randomString()}`
		await owner.createTeam({ name })

		await page.goto('/index.php/apps/collectives')
		await navigation.createCollective(name)

		await expect(titleBar.title).toHaveValue(name)
		await expect(getToast(page, 'success', `Created collective "${name}" for existing team.`)).toBeVisible()
	})
})
