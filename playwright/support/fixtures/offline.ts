/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CDPSession } from '@playwright/test'

import { test as base } from '@playwright/test'

interface OfflineFixture {
	setOffline: () => Promise<void>
	setOnline: () => Promise<void>
}

/**
 * Turn the network on again after it was turned off with setOffline.
 *
 * @param client - The CDP session to use for sending the commands to turn the network on/off.
 */
async function setClientOnline(client: CDPSession): Promise<void> {
	await client.send('Network.emulateNetworkConditions', {
		offline: false,
		latency: 0,
		downloadThroughput: -1,
		uploadThroughput: -1,
	})
	await client.send('Network.disable')
}

/**
 * Turn the network off for the rest of the test until turned on again with setOnline.
 *
 * @param client - The CDP session to use for sending the commands to turn the network on/off.
 */
async function setClientOffline(client: CDPSession): Promise<void> {
	await client.send('Network.enable')
	await client.send('Network.emulateNetworkConditions', {
		offline: true,
		latency: 0,
		downloadThroughput: 0,
		uploadThroughput: 0,
	})
}

/**
 * setOffline turns the network off until setOnline turns it on again.
 * Both use the same CDP session, as network emulation is applied per session.
 */
export const test = base.extend<OfflineFixture & { networkClient: CDPSession }>({
	networkClient: async ({ context, page }, use) => {
		const client = await context.newCDPSession(page)
		await use(client)
		await setClientOnline(client)
	},
	setOffline: async ({ networkClient }, use) => {
		await use(() => setClientOffline(networkClient))
	},
	setOnline: async ({ networkClient }, use) => {
		await use(() => setClientOnline(networkClient))
	},
})
