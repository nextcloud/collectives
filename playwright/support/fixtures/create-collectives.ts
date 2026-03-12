/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Collective } from './Collective.ts'

import { runOcc } from '@nextcloud/e2e-test-server/docker'
import { randomString } from '../helpers/randomString.ts'
import { test as base } from './random-user.ts'

export interface CollectiveConfig {
	name: string
	emoji?: string // optional
	markdownImportPath?: string // optional
	pages?: {
		title: string
		parentId?: number // optional, defaults to 0 (root page)
		content?: string // optional
	}[]
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
			{ name: randomString(), emoji: '🌟' },
		])
	},
	collectives: async ({ collectiveConfigs, user, page }, use) => {
		const createdCollectives: Collective[] = []

		// Create all collectives
		for (const config of collectiveConfigs) {
			const collective = await user.createCollective(config, page)

			// Import Markdown if path is provided
			if (config.markdownImportPath) {
				await runOcc([
					'collectives:import:markdown',
					`--collective-id=${collective.data.id}`,
					`--user-id=${user.account.userId}`,
					'--',
					config.markdownImportPath,
				])
			}

			if (config.pages) {
				for (const collectivePage of config.pages) {
					const createdPage = await collective.createPage({
						title: collectivePage.title,
						parentId: collectivePage.parentId || 0,
						content: collectivePage.content,
						user,
						page,
					})
					collective.collectivePages.push(createdPage)
				}
			}

			createdCollectives.push(collective)
		}

		await use(createdCollectives)

		// Cleanup all collectives
		for (const collective of createdCollectives) {
			await user.deleteCollective({ id: collective.data.id }, page)
		}
	},
	collective: async ({ collectives }, use) => {
		if (!collectives[0]) {
			throw new Error('No collective available for the test')
		}
		await use(collectives[0])
	},
})
