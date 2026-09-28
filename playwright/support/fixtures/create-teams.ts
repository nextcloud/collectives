/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Team, TeamConfig } from './Team.ts'

import { randomString } from '../helpers/randomString.ts'
import { test as base } from './random-user.ts'
import { createTeam } from './Team.ts'

export interface TeamsFixture {
	teamConfigs: TeamConfig[]
	teams: Team[]
	team: Team
}

/**
 * This test fixture creates teams (circles) for the user and makes them available for the test.
 *
 * To customize teams, extend this fixture in your test file:
 * ```
 * const test = createTeamsTest.extend<{}>({
 *   teamConfigs: async ({}, use) => use([
 *     { name: 'Custom Team' },
 *     { name: 'Public Team', visible: true, open: true },
 *   ]),
 * })
 *
 * test('My test', async ({ teams }) => {
 *   // teams[0].data.sanitizedName === 'Custom Team'
 * })
 * ```
 */
export const test = base.extend<TeamsFixture>({
	// eslint-disable-next-line no-empty-pattern
	teamConfigs: async ({}, use) => {
		await use([
			{ name: randomString() },
		])
	},
	teams: async ({ teamConfigs, page }, use) => {
		const createdTeams: Team[] = []

		for (const config of teamConfigs) {
			createdTeams.push(await createTeam({ ...config, page }))
		}

		await use(createdTeams)

		// Cleanup all teams
		for (const team of createdTeams) {
			await team.delete()
		}
	},
	team: async ({ teams }, use) => {
		if (!teams[0]) {
			throw new Error('No team available for the test')
		}
		await use(teams[0])
	},
})
