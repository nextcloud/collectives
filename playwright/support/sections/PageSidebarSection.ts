/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Locator, type Page } from '@playwright/test'
import { expect } from '@playwright/test'

type SidebarTab = 'Attachments' | 'Backlinks' | 'Sharing' | 'Versions'

export class PageSidebarSection {
	public readonly el: Locator

	constructor(public readonly page: Page) {
		this.el = this.page.locator('.app-sidebar')
	}

	public async openSidebar(): Promise<void> {
		await this.page.getByRole('button', { name: 'Open sidebar' }).click()
		await expect(this.el).toBeVisible()
	}

	public async closeSidebar(): Promise<void> {
		await this.page.getByRole('button', { name: 'Close sidebar' }).click()
		await expect(this.el).not.toBeVisible()
	}

	public async openSidebarTab(name: SidebarTab): Promise<Locator> {
		if (!(await this.el.isVisible())) {
			await this.openSidebar()
		}
		await this.el.getByRole('tab', { name }).click()
		return this.el.getByRole('tabpanel', { name })
	}

	public getVersionListItem(name: string): Locator {
		return this.el.locator('.version-list .list-item').filter({ hasText: name })
	}
}
