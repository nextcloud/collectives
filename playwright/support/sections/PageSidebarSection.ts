/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

type SidebarTab = 'Attachments' | 'Backlinks' | 'Sharing' | 'Versions'
type AttachmentList = 'embedded' | 'not-embedded' | 'deleted'

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

	public getAttachmentList(list: AttachmentList): Locator {
		return this.el.locator(`.attachment-list-${list}`)
	}

	public getAttachmentListItem(name: string): Locator {
		return this.el.locator('.attachment').filter({ hasText: name })
	}

	public async clickAttachmentAction(name: string, action: string): Promise<void> {
		await this.getAttachmentListItem(name)
			.getByRole('button', { name: 'Actions' })
			.click()
		await this.page.getByRole('menuitem', { name: action }).click()
	}

	public getShareEntry(sharingTab: Locator, title: string): Locator {
		return sharingTab.locator('.sharing-entry')
			.filter({ has: this.page.locator('.sharing-entry__title', { hasText: title }) })
	}

	public async openShareMenu(entry: Locator): Promise<void> {
		await entry.locator('.sharing-entry__actions')
			.getByRole('button', { name: 'Actions' }).click()
	}

	/**
	 * Click an action in the menu of a share entry
	 *
	 * @param sharingTab The sharing tab
	 * @param actionName Name of the menu action
	 * @param entry The share entry, defaults to the only entry in the sharing tab
	 */
	public async clickShareMenuAction(sharingTab: Locator, actionName: string, entry: Locator = sharingTab.locator('.sharing-entry')): Promise<void> {
		await this.openShareMenu(entry)
		await this.page.getByRole('menuitem', { name: actionName }).click()
	}

	public getShareSettingsPanel(sharingTab: Locator): Locator {
		return sharingTab.locator('.sharing-entry__settings')
	}

	public async selectShareDropdownOption(sharingTab: Locator, option: string): Promise<void> {
		const entry = sharingTab.locator('.sharing-entry')
		await entry.locator('.trigger-text').click()
		await entry.locator('.dropdown-item').filter({ hasText: option }).click()
	}
}
