/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { apiUrl, ocsHeaders } from '../helpers/urls.ts'

export type CollectiveShareData = {
	id: number
	collectiveId: number
	pageId: number
	token: string
	owner: string
	editable: boolean
	hasPassword: boolean
}

export class CollectiveShare {
	public readonly collectiveUrlPart: string
	public data: CollectiveShareData
	public readonly page: Page

	constructor(collectiveUrlPart: string, data: CollectiveShareData, page: Page) {
		this.collectiveUrlPart = collectiveUrlPart
		this.data = data
		this.page = page
	}

	getShareUrl() {
		return `/index.php/apps/collectives/p/${this.data.token}/${this.collectiveUrlPart}`
	}

	/**
	 * Open the collective share in the browser.
	 */
	async open() {
		await this.page.goto(this.getShareUrl())
	}

	async setEditable(editable: boolean) {
		await this.page.request.put(
			apiUrl('v1.0', 'collectives', this.data.collectiveId, 'shares', this.data.token),
			{
				headers: ocsHeaders,
				data: JSON.stringify({
					editable,
				}),
				failOnStatusCode: true,
			},
		)
	}

	async delete() {
		await this.page.request.delete(
			apiUrl('v1.0', 'collectives', this.data.collectiveId, 'shares', this.data.token),
			{
				headers: ocsHeaders,
				failOnStatusCode: true,
			},
		)
	}

	async updateData(): Promise<CollectiveShareData> {
		const response = await this.page.request.get(
			apiUrl('v1.0', 'collectives', this.data.collectiveId, 'shares'),
			{ headers: ocsHeaders, failOnStatusCode: true },
		)
		const json = await response.json()
		const share = json.ocs.data.find((s: CollectiveShareData) => s.token === this.data.token)
		if (!share) {
			throw new Error(`Share with token ${this.data.token} not found`)
		}
		this.data = share
		return share
	}
}
