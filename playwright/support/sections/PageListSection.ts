/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

export class PageListSection {
	public readonly el: Locator
	public readonly pageListItems: Locator

	constructor(public readonly page: Page) {
		this.el = this.page.locator('.app-content-list')
		this.pageListItems = this.el.locator('.app-content-list-item')
	}

	public getPageItem(title: string): Locator {
		return this.pageListItems.filter({ hasText: title })
	}

	public async expectPageListOrder(titles: string[]): Promise<void> {
		await expect(this.pageListItems).toContainText(titles)
	}

	public async toggleExpandPage(title: string): Promise<void> {
		await this.getPageItem(title)
			.locator('.item-icon-badge')
			.click()
	}
}
