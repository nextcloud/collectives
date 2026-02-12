/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { type User } from './User.ts'

export class Collective {
	public readonly id: number
	public readonly slug?: string
	public readonly circleId: string
	public readonly name: string
	public readonly emoji?: string
	public readonly pageMode: number
	public readonly level: number
	public readonly editPermissionLevel: number
	public readonly sharePermissionLevel: number
	public readonly canEdit: boolean
	public readonly canShare: boolean
	public readonly shareToken?: string
	public readonly isPageShare: boolean
	public readonly sharePageId?: number
	public readonly shareEditable: boolean
	public readonly userPageOrder: number
	public readonly userShowMembers: boolean
	public readonly userShowRecentPages: boolean
	public readonly userFavoritePages: number[]
	public readonly canLeave: boolean
	public readonly trashTimestamp?: number
	public readonly page: Page

	constructor(data: {
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
		page: Page
	}) {
		this.id = data.id
		this.slug = data.slug
		this.circleId = data.circleId
		this.name = data.name
		this.emoji = data.emoji
		this.pageMode = data.pageMode
		this.level = data.level
		this.editPermissionLevel = data.editPermissionLevel
		this.sharePermissionLevel = data.sharePermissionLevel
		this.canEdit = data.canEdit
		this.canShare = data.canShare
		this.shareToken = data.shareToken
		this.isPageShare = data.isPageShare
		this.sharePageId = data.sharePageId
		this.shareEditable = data.shareEditable
		this.userPageOrder = data.userPageOrder
		this.userShowMembers = data.userShowMembers
		this.userShowRecentPages = data.userShowRecentPages
		this.userFavoritePages = data.userFavoritePages
		this.canLeave = data.canLeave
		this.trashTimestamp = data.trashTimestamp
		this.page = data.page
	}

	async open() {
		const path = this.slug
			? `${this.slug}-${this.id}`
			: encodeURIComponent(this.name)
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
	return new Collective({ ...data.ocs.data.collective, page: user.page })
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
