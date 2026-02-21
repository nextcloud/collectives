/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Page } from '@playwright/test'
import { apiUrl } from './urls.ts'
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

const ocsHeaders = {
	'OCS-APIRequest': 'true',
	Accept: 'application/json',
	'Content-Type': 'application/json',
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

	async openCollective({ pageTitle }: { pageTitle?: string } = {}) {
		const { slug, id, name } = this.data
		const collectivePath = slug
			? `${slug}-${id}`
			: encodeURIComponent(name)
		let pagePath = ''
		if (pageTitle) {
			pagePath = `/${pageTitle}`
		}
		await this.page.goto(`/index.php/apps/collectives/${collectivePath}${pagePath}`)
		await this.waitForReaderContent()
	}

	getReaderContent() {
		return this.page.locator('[data-cy-collectives="reader"] .ProseMirror')
	}

	/**
	 * Wait for the collective landing page to finish loading.
	 */
	async waitForReaderContent() {
		await this.getReaderContent()
			.waitFor({ state: 'visible' })
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
	const response = await user.request.post(
		apiUrl('v1.0', 'collectives'),
		{
			headers: ocsHeaders,
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
	const trashResponse = await user.request.delete(
		apiUrl('v1.0', 'collectives', id),
		{ headers: ocsHeaders },
	)
	if (!trashResponse.ok()) {
		throw new Error(`Failed to trash collective: ${trashResponse.status()} - ${trashResponse.statusText()}`)
	}
	const deleteResponse = await user.request.delete(
		apiUrl('v1.0', 'collectives', 'trash', id),
		{
			headers: ocsHeaders,
			data: {
				circle: true,
			},
		},
	)
	if (!deleteResponse.ok()) {
		throw new Error(`Failed to delete collective: ${deleteResponse.status()} - ${deleteResponse.statusText()}`)
	}
}
