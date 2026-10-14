/**
* SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
* SPDX-License-Identifier: AGPL-3.0-or-later
*/

import type { Page } from '@playwright/test'

import { trashAndDeleteCollective } from '../fixtures/Collective.ts'
import { apiUrl, circlesApiUrl, ocsHeaders } from './urls.ts'

const CIRCLE_CONFIG_VISIBLE = 8
const CIRCLE_CONFIG_OPEN = 16

/**
 * Create a team (circle) and optionally make it visible and open.
 *
 * @param options options for the team
 * @param options.name Name of the team
 * @param options.visible Whether the team is visible to non-members
 * @param options.open Whether anyone can join the team
 * @param options.page the Playwright page of the team owner
 * @return ID of the created team
 */
export async function createTeam({ name, visible = false, open = false, page }: {
	name: string
	visible?: boolean
	open?: boolean
	page: Page
}): Promise<string> {
	const response = await page.request.post(
		circlesApiUrl(),
		{
			headers: ocsHeaders,
			data: { name, personal: false },
			failOnStatusCode: true,
		},
	)
	const data = await response.json()
	const circleId = data.ocs.data.id

	const value = (visible ? CIRCLE_CONFIG_VISIBLE : 0) + (open ? CIRCLE_CONFIG_OPEN : 0)
	if (value) {
		await page.request.put(
			circlesApiUrl(circleId, 'config'),
			{ headers: ocsHeaders, data: { value }, failOnStatusCode: true },
		)
	}
	return circleId
}

/**
 * Delete the collective with the given name including its team,
 * or only the team if no collective exists for it.
 *
 * @param options options for the collective or team
 * @param options.name Name of the collective or team
 * @param options.page the Playwright page of the team owner
 */
export async function deleteCollectiveOrTeam({ name, page }: {
	name: string
	page: Page
}): Promise<void> {
	const collectivesResponse = await page.request.get(
		apiUrl('v1.0', 'collectives'),
		{ headers: ocsHeaders, failOnStatusCode: true },
	)
	const collective = (await collectivesResponse.json()).ocs.data.collectives
		.find((c: { name: string }) => c.name === name)
	if (collective) {
		await trashAndDeleteCollective({ id: collective.id, page })
		return
	}

	const circlesResponse = await page.request.get(
		circlesApiUrl(),
		{ headers: ocsHeaders, failOnStatusCode: true },
	)
	const circle = (await circlesResponse.json()).ocs.data
		.find((c: { sanitizedName: string }) => c.sanitizedName === name)
	if (circle) {
		await page.request.delete(
			circlesApiUrl(circle.id),
			{ headers: ocsHeaders, failOnStatusCode: true },
		)
	}
}
