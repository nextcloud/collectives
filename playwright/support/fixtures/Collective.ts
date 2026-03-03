/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { User } from './User.ts'

import { CollectivePage } from './CollectivePage.ts'
import { apiUrl } from './urls.ts'

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
	public collectivePages: CollectivePage[] = []
	public readonly page: Page

	constructor(data: CollectiveData, page: Page) {
		this.data = data
		this.page = page
	}

	static async create(data: CollectiveData, page: Page): Promise<Collective> {
		const collective = new Collective(data, page)
		collective.collectivePages = await collective.getPages()
		return collective
	}

	getCollectiveUrlPart() {
		return this.data.slug
			? `${this.data.slug}-${this.data.id}`
			: encodeURIComponent(this.data.name)
	}

	getRootPageId() {
		const rootPage = this.collectivePages.find((page) => page.data.parentId === 0)
		if (!rootPage) {
			throw new Error(`Root page not found for collective "${this.data.name}"`)
		}
		return rootPage.data.id
	}

	getPageByTitle(title: string) {
		const page = this.collectivePages.find((page) => page.data.title === title)
		if (!page) {
			throw new Error(`Page with title "${title}" not found in collective "${this.data.name}"`)
		}
		return page
	}

	async openApp() {
		await this.page.goto('/index.php/apps/collectives')
	}

	async openCollective({ pageTitle }: { pageTitle?: string } = {}) {
		let pagePath = ''
		if (pageTitle) {
			pagePath = `/${pageTitle}`
		}
		await this.page.goto(`/index.php/apps/collectives/${this.getCollectiveUrlPart()}${pagePath}`)
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

	async getPages() {
		const response = await this.page.request.get(
			apiUrl('v1.0', 'collectives', this.data.id, 'pages'),
			{ headers: ocsHeaders, failOnStatusCode: true },
		)
		const data = await response.json()
		/* eslint-disable-next-line @typescript-eslint/no-explicit-any */
		return data.ocs.data.pages.map((pageData: any) => new CollectivePage(this.data.id, this.getCollectiveUrlPart(), pageData, this.page))
	}

	/**
	 * Create a page in the collective.
	 *
	 * @param options options for creating the page
	 * @param options.title title of the page
	 * @param options.content content of the page (optional)
	 * @param options.parentId ID of the parent page (optional, defaults to root page)
	 * @param options.user user to authenticate the request with
	 */
	async createPage({ title, content = null, parentId = 0, user }: {
		title: string
		parentId?: number
		content?: string | null
		user: User
	}) {
		if (parentId === 0) {
			parentId = this.getRootPageId()
		}
		const response = await user.request.post(
			apiUrl('v1.0', 'collectives', this.data.id, 'pages', parentId),
			{
				headers: ocsHeaders,
				data: {
					title,
					parentId,
				},
				failOnStatusCode: true,
			},
		)
		const data = await response.json()
		const collectivePage = new CollectivePage(this.data.id, this.getCollectiveUrlPart(), data.ocs.data.page, user.page)

		if (content) {
			await collectivePage.setContent({ content, user })
		}

		return collectivePage
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
			failOnStatusCode: true,
		},
	)
	const data = await response.json()
	return Collective.create(data.ocs.data.collective, user.page)
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
	await user.request.delete(
		apiUrl('v1.0', 'collectives', id),
		{ headers: ocsHeaders, failOnStatusCode: true },
	)
	await user.request.delete(
		apiUrl('v1.0', 'collectives', 'trash', id),
		{
			headers: ocsHeaders,
			data: {
				circle: true,
			},
			failOnStatusCode: true,
		},
	)
}
