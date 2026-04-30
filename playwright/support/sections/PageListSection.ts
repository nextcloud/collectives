/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

export class PageListSection {
	public readonly el: Locator
	public readonly pageListItems: Locator
	public readonly filter: Locator
	public readonly filterTagSelect: Locator
	public readonly activeFilterTags: Locator

	constructor(public readonly page: Page) {
		this.el = this.page.locator('.app-content-list')
		this.pageListItems = this.el.locator('.app-content-list-item')
		this.filter = this.el.getByRole('textbox', { name: 'Search pages' })
		this.filterTagSelect = this.page.locator('.page-filter-tag-select')
		this.activeFilterTags = this.el.locator('.page-filter-tags')
	}

	public getPageItem(title: string): Locator {
		return this.pageListItems.filter({ hasText: title, visible: true })
	}

	public async expectPageListOrder(titles: string[]): Promise<void> {
		await expect(this.pageListItems).toContainText(titles)
	}

	public async toggleExpandPage(title: string): Promise<void> {
		await this.getPageItem(title)
			.locator('.item-icon-badge')
			.click()
	}

	public async addPage(parentTitle: string): Promise <void> {
		await this.getPageItem(parentTitle)
			.getByRole('button', { name: 'Add a page' })
			.click()
	}
}
