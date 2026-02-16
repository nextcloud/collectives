/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { type User } from './User.ts'

type CollectiveData = {
	id: number
	slug?: string
	circleId: string
	name: string
	emoji?: string
	pageMode: number
	level: number
	editPermissionLevel: number
	sharePermissionLevel: number
	canEdit: boolean
	canShare: boolean
	shareToken?: string
	isPageShare: boolean
	sharePageId?: number
	shareEditable: boolean
	userPageOrder: number
	userShowMembers: boolean
	userShowRecentPages: boolean
	userFavoritePages: number[]
	canLeave: boolean
	trashTimestamp?: number
}

export class Collective {
	public readonly data: CollectiveData
	public readonly page: Page

	constructor(data: CollectiveData, page: Page) {
		this.data = data
		this.page = page
	}

	async openApp() {
		await this.page.goto('/index.php/apps/collectives')
	}

	async openCollective() {
		const { slug, id, name } = this.data
		const path = slug
			? `${slug}-${id}`
			: encodeURIComponent(name)
		await this.page.goto(`/index.php/apps/collectives/${path}`)
		await this.waitForLoad()
	}

	/**
	 * Wait for the collective landing page to finish loading.
	 */
	async waitForLoad() {
		// Wait for the page content to be visible
		await this.page.locator('.ProseMirror').waitFor({ state: 'visible' })
	}
}

/**
 * Create a collective.
 *
 * @param options options for the collective
 * @param options.name Name of the collective
 * @param options.emoji Emoji of the collective (optional)
 * @param options.user User who creates the collective
 * @return The created collective
 */
export async function createCollective({ name, emoji = '', user }: {
	name: string
	emoji?: string
	user: User
}) {
	const headers = {
		Accept: 'application/json',
		'Content-Type': 'application/json',
		'OCS-APIRequest': 'true',
	}
	const response = await user.request.post(
		'/ocs/v2.php/apps/collectives/api/v1.0/collectives',
		{
			headers,
			data: {
				name,
				emoji,
			},
		},
	)
	if (!response.ok()) {
		throw new Error(`Failed to create collective ${name}: ${response.status()} - ${response.statusText()}`)
	}
	const data = await response.json()
	return new Collective(data.ocs.data.collective, user.page)
}

/**
 * Trash and delete a collective.
 *
 * @param options options for the collective
 * @param options.id ID of the collective
 * @param options.user User who trashes and deletes the collective
 */
export async function trashAndDeleteCollective({ id, user }: {
	id: number
	user: User
}) {
	const headers = {
		'OCS-APIRequest': 'true',
	}
	const trashResponse = await user.request.delete(
		`/ocs/v2.php/apps/collectives/api/v1.0/collectives/${id}`,
		{ headers },
	)
	if (!trashResponse.ok()) {
		throw new Error(`Failed to trash collective: ${trashResponse.status()} - ${trashResponse.statusText()}`)
	}
	const deleteResponse = await user.request.delete(
		`/ocs/v2.php/apps/collectives/api/v1.0/collectives/trash/${id}`,
		{
			headers,
			data: {
				circle: true,
			},
		},
	)
	if (!deleteResponse.ok()) {
		throw new Error(`Failed to delete collective: ${deleteResponse.status()} - ${deleteResponse.statusText()}`)
	}
}
