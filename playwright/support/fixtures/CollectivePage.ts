/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { User } from './User.ts'

import { webdavUrl } from './urls.ts'

type CollectivePageData = {
	id: number
	slug: string
	lastUserId?: string
	lastUserDisplayName?: string
	emoji?: string
	isFullWidth: boolean
	subpageOrder: number[]
	trashTimestamp?: number
	title: string
	timestamp: number
	size: number
	fileName: string
	filePath: string
	collectivePath: string
	parentId: number
	shareToken?: string
}

export class CollectivePage {
	public readonly collectiveId: number
	public readonly collectiveUrlPart: string
	public readonly data: CollectivePageData
	public readonly page: Page

	constructor(collectiveId: number, collectiveUrlPart: string, data: CollectivePageData, page: Page) {
		this.collectiveId = collectiveId
		this.collectiveUrlPart = collectiveUrlPart
		this.data = data
		this.page = page
	}

	getPageUrlPart() {
		return this.data.slug
			? `${this.data.slug}-${this.data.id}`
			: encodeURIComponent(this.data.title)
	}

	getPageUrl() {
		return `/index.php/apps/collectives/${this.collectiveUrlPart}/${this.getPageUrlPart()}`
	}

	/**
	 * Open the collective page in the browser.
	 *
	 * @param edit whether page is expected to open in edit mode
	 */
	async open(edit: boolean = false) {
		await this.page.goto(this.getPageUrl())
		await this.waitForContent(edit)
	}

	/**
	 * Get the content locator for the page.
	 *
	 * @param edit whether to get content from editor or reader
	 */
	getContent(edit: boolean = false) {
		const mode = edit ? 'editor' : 'reader'
		return this.page.locator(`[data-cy-collectives="${mode}"] .ProseMirror`)
	}

	/**
	 * Wait for the collective landing page to finish loading.
	 *
	 * @param edit whether page is expected to open in edit mode
	 */
	async waitForContent(edit: boolean = false) {
		await this.getContent(edit)
			.waitFor({ state: 'visible' })
	}

	/**
	 * Set page content via WebDAV.
	 *
	 * @param options the options
	 * @param options.content the content to set
	 * @param options.user the user to authenticate the request with
	 */
	async setContent({ content, user }: {
		content: string
		user: User
	}) {
		await user.request.put(
			webdavUrl(user.account.userId, this.data.collectivePath, this.data.filePath, this.data.fileName),
			{
				headers: {
					'Content-Type': 'text/markdown',
				},
				data: content,
				failOnStatusCode: true,
			},
		)
	}

	/**
	 * Set page content to contain a link via WebDAV.
	 *
	 * @param options the options
	 * @param options.linkText the text of the link
	 * @param options.linkUrl the URL of the link
	 * @param options.user the user to authenticate the request with
	 */
	async setLinkContent({ linkText, linkUrl, user }: {
		linkText: string
		linkUrl: string
		user: User
	}) {
		const content = `## Link\n\n[${linkText}](${linkUrl})`
		await this.setContent({ content, user })
	}
}
