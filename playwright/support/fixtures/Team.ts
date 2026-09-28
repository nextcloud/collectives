/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { circlesApiUrl, ocsHeaders } from '../helpers/urls.ts'
import { findCollectiveByCircleId, trashAndDeleteCollective } from './Collective.ts'

/** Team (circle) config bits, see `OCA\Circles\Model\Circle` */
const CFG_VISIBLE = 8
const CFG_OPEN = 16

export interface TeamConfig {
	name: string
	visible?: boolean // optional, defaults to false
	open?: boolean // optional, defaults to false
}

export type TeamData = {
	id: string
	name: string
	sanitizedName: string
}

export class Team {
	constructor(public readonly data: TeamData, public readonly page: Page) {
	}

	async setConfig(value: number): Promise<void> {
		await this.page.request.put(
			circlesApiUrl(this.data.id, 'config'),
			{ headers: ocsHeaders, data: { value }, failOnStatusCode: true },
		)
	}

	/**
	 * Delete the team.
	 *
	 * If a collective was created for the team in the meantime, deleteone instead:
	 * teams managed by the collectives app cannot be destroyed via thecircles API.
	 */
	async delete(): Promise<void> {
		const collective = await findCollectiveByCircleId({ circleId: this.data.id, page: this.page })
		if (collective) {
			await trashAndDeleteCollective({ id: collective.id, page: this.page })
			return
		}
		await this.page.request.delete(
			circlesApiUrl(this.data.id),
			{ headers: ocsHeaders, failOnStatusCode: true },
		)
	}
}

/**
 * Create a team (circle).
 *
 * @param options options for the team
 * @param options.name Name of the team
 * @param options.visible Whether the team is visible to everyone (optional)
 * @param options.open Whether the team can be joined by everyone (optional)
 * @param options.page the Playwright page
 * @return The created team
 */
export async function createTeam({ name, visible = false, open = false, page }: TeamConfig & {
	page: Page
}): Promise<Team> {
	const response = await page.request.post(
		circlesApiUrl(),
		{
			headers: ocsHeaders,
			data: {
				name,
				personal: false,
				createTeamFolder: false,
			},
			failOnStatusCode: true,
		},
	)
	const data = await response.json()
	const team = new Team(data.ocs.data, page)

	if (visible || open) {
		await team.setConfig((visible ? CFG_VISIBLE : 0) + (open ? CFG_OPEN : 0))
	}

	return team
}
