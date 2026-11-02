/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as pageListTest } from '../support/fixtures/pageList.ts'
import { test as titleBarTest } from '../support/fixtures/titleBar.ts'

const test = mergeTests(createCollectivesTest, pageListTest, titleBarTest)

type EventBusWindow = { _nc_event_bus: { emit: (name: string, event?: object) => void } }

type Signal = { promise: Promise<void>, resolve: () => void }

/**
 * Create a promise together with the function that resolves it.
 *
 * Like `Promise.withResolvers()`, which isn't in the TypeScript lib configured for this repo.
 */
function createSignal(): Signal {
	let resolve: () => void = () => {}
	const promise = new Promise<void>((r) => {
		resolve = r
	})
	return { promise, resolve }
}

/**
 * Emit an event on the Nextcloud event bus in the browser page.
 *
 * @param page - the browser page
 * @param name - name of the event
 * @param event - optional event payload
 */
async function emitEvent(page: Page, name: string, event?: object): Promise<void> {
	await page.evaluate(({ name, event }) => {
		(window as unknown as EventBusWindow)._nc_event_bus.emit(name, event)
	}, { name, event })
}

/**
 * Start a page list request and hold back its response.
 *
 * Simulates a slow page list request, e.g. from the background poller, that overlaps
 * with a change to the page list:
 *
 * 1. This function makes the app request the page list. The request reaches the server
 *    right away, so the response contains the page list from before the change.
 * 2. The test changes the page list, e.g. creates or renames a page.
 * 3. The test calls the returned function, and the app receives the outdated response.
 *
 * @param page - the browser page
 * @param collectiveId - ID of the collective
 * @return Function that passes the held back response on to the app
 */
async function startStalePageListRequest(page: Page, collectiveId: number): Promise<() => Promise<void>> {
	// Resolved when the response from the server is held back
	const responseHeld = createSignal()
	// Resolved by the test to pass the response on to the app
	const responseReleased = createSignal()
	// Resolved when the app got the response or aborted the request
	const responseDelivered = createSignal()

	// Further page list requests of the app, e.g. to refresh the page list after a change
	let otherRequestsInFlight = 0
	let otherRequestsLastActive = Date.now()

	// Intercept page list request, but not requests for single pages or the trash
	const pageListUrl = new RegExp(`/collectives/${collectiveId}/pages(\\?.*)?$`)
	let isFirstRequest = true
	await page.route(pageListUrl, async (route) => {
		if (!isFirstRequest) {
			// Pass further requests through, but keep track of them
			otherRequestsInFlight++
			try {
				await route.fulfill({ response: await route.fetch() })
			} catch {
				// The app aborted the request in the meantime
			} finally {
				otherRequestsInFlight--
				otherRequestsLastActive = Date.now()
			}
			return
		}
		isFirstRequest = false

		// Send the request to the server now, so the response predates the change
		const response = await route.fetch()
		responseHeld.resolve()

		await responseReleased.promise
		try {
			await route.fulfill({ response })
		} catch {
			// The app aborted the request in the meantime
		}
		responseDelivered.resolve()
	})

	// The app fetches the page list when the network comes back online, like the background
	// poller does. Separate calls, as the app doesn't notice an offline state within one task.
	await emitEvent(page, 'networkOffline')
	await emitEvent(page, 'networkOnline', { success: true })
	await responseHeld.promise

	return async () => {
		// Like a slow request, the stale response arrives after the responses to further
		// page list requests. Wait until there was none in flight for half a second.
		await expect.poll(() => otherRequestsInFlight === 0 && Date.now() - otherRequestsLastActive >= 500).toBe(true)

		responseReleased.resolve()
		await responseDelivered.promise
		// Give the app time to process the response and render the page list
		await page.evaluate(() => new Promise((resolve) => requestAnimationFrame(() => setTimeout(resolve))))
	}
}

test.describe('Page list with concurrent requests', () => {
	test('keeps a new page when an older page list response arrives', async ({ page, collective, pageList }) => {
		await collective.openCollective()
		const releaseStaleResponse = await startStalePageListRequest(page, collective.data.id)

		await pageList.addTopLevelPage()
		await expect(pageList.getPageItem('New page')).toBeVisible()

		await releaseStaleResponse()
		await expect(pageList.getPageItem('New page')).toBeVisible()
		await expect(page.getByText(/Page with ID \d+ not found/)).toBeHidden()
	})

	test('keeps a renamed page when an older page list response arrives', async ({ page, user, collective, pageList, titleBar }) => {
		// With content, so that the page opens in view mode
		await collective.createPage({ title: 'Old title', content: 'Some content', user, page })
		await collective.openCollective({ pageTitle: 'Old title' })
		const releaseStaleResponse = await startStalePageListRequest(page, collective.data.id)

		await titleBar.title.fill('New title')
		// The page gets renamed when the title field loses focus
		await titleBar.title.blur()
		await expect(pageList.getPageItem('New title')).toBeVisible()

		await releaseStaleResponse()
		await expect(pageList.getPageItem('New title')).toBeVisible()
		await expect(pageList.getPageItem('Old title')).toHaveCount(0)
		await expect(page).toHaveURL(/\/New-title/)
	})
})
