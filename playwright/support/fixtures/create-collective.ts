/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Collective } from './Collective.ts'
import { test as base } from './random-user.ts'

export interface CollectiveFixture {
	collectiveName: string
	collectiveEmoji: string
	collective: Collective
}

/**
 * This test fixture creates a collective for the user and makes it available for the test.
 * You can customize the collective name and emoji by using test.use():
 *
 * test.use({ collectiveName: 'My Custom Name', collectiveEmoji: '🚀' })
 */
export const test = base.extend<CollectiveFixture>({
	collectiveName: 'Test Collective',
	collectiveEmoji: '🌟',
	collective: async ({ collectiveName, collectiveEmoji, user }, use) => {
		const collective = await user.createCollective({ name: collectiveName, emoji: collectiveEmoji })
		await use(collective)
		await user.deleteCollective({ id: collective.id })
	},
})
