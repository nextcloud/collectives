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
	 * Switch the page mode between edit and preview mode.
	 *
	 * @param edit whether to switch to edit or preview mode
	 */
	async switchMode(edit: boolean) {
		if (await this.hasMode(edit)) {
			return
		}

		const label = edit ? 'Edit' : 'Preview'
		await this.page.locator('.edit-button')
			.getByLabel(label)
			.click()
		await this.waitForContent(edit)
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
	 * Check if the page is in edit/preview mode.
	 *
	 * @param edit whether page is expected to be in edit or preview mode
	 */
	async hasMode(edit: boolean = false) {
		return await this.getContent(edit).isVisible()
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
	 * @param options.page the Playwright page
	 */
	async setContent({ content, user, page }: {
		content: string
		user: User
		page: Page
	}) {
		await page.request.put(
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
	 * @param options.page the Playwright page
	 */
	async setLinkContent({ linkText, linkUrl, user, page }: {
		linkText: string
		linkUrl: string
		user: User
		page: Page
	}) {
		const content = `## Link\n\n[${linkText}](${linkUrl})`
		await this.setContent({ content, user, page })
	}
}
