/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { type Collective } from './Collective.ts'
import { test as base } from './random-user.ts'

export interface CollectiveConfig {
	name: string
	emoji?: string // optional
	markdownImportPath?: string // optional
}

export interface CollectivesFixture {
	collectiveConfigs: CollectiveConfig[]
	collectives: Collective[]
	collective: Collective
}

/**
 * This test fixture creates multiple collectives for the user and makes them available for the test.
 *
 * To customize collectives, extend this fixture in your test file:
 * ```
 * const test = createCollectiveTest.extend<{}>({
 *   collectiveConfigs: async ({}, use) => use([
 *     { name: 'Custom 1', emoji: '🎯' },
 *     { name: 'Custom 2', emoji: '🚀' },
 *   ]),
 * })
 *
 * test('My test', async ({ collectives }) => {
 *   // collectives[0].name === 'Custom 1'
 * })
 * ```
 */
export const test = base.extend<CollectivesFixture>({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => {
		await use([
			{ name: 'Test Collective 1', emoji: '🌟' },
		])
	},
	collectives: async ({ collectiveConfigs, user }, use) => {
		const createdCollectives: Collective[] = []

		// Create all collectives
		for (const config of collectiveConfigs) {
			const collective = await user.createCollective(config)
			createdCollectives.push(collective)

			// Import Markdown if path is provided
			if (config.markdownImportPath) {
				await runOcc([
					'collectives:import:markdown',
					`--collective-id=${collective.data.id}`,
					`--user-id=${user.userId}`,
					'--',
					config.markdownImportPath,
				])
			}
		}

		await use(createdCollectives)

		// Cleanup all collectives
		for (const collective of createdCollectives) {
			await user.deleteCollective({ id: collective.data.id })
		}
	},
	collective: async ({ collectives }, use) => {
		await use(collectives[0])
	},
})
