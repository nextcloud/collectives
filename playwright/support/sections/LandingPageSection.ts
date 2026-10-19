/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export class LandingPageSection {
	public readonly el: Locator
	public readonly recentPagesWidget: Locator
	public readonly recentPageTiles: Locator
	public readonly membersWidget: Locator

	constructor(public readonly page: Page) {
		this.el = this.page.locator('.landing-page-widgets')
		this.recentPagesWidget = this.el.locator('.recent-pages-widget')
		this.recentPageTiles = this.recentPagesWidget.locator('.recent-page-tile')
		this.membersWidget = this.el.locator('.members-widget')
	}

	public getRecentPageTile(title: string): Locator {
		return this.recentPageTiles.filter({ hasText: title })
	}

	public getMemberAvatar(userId: string): Locator {
		return this.membersWidget.locator(`img[src*="/avatar/${userId}/"]`)
	}

	public getMembersButton(isAdmin: boolean): Locator {
		return this.membersWidget.getByRole('button', {
			name: isAdmin ? 'Manage members of the collective' : 'Show all members of the collective',
		})
	}

	/**
	 * Toggle the recent pages widget and wait for the setting to be saved.
	 */
	public async toggleRecentPages(): Promise<void> {
		await this.toggleWidget(/^(Collapse|Expand) recent pages$/, 'showRecentPages')
	}

	/**
	 * Toggle the members widget and wait for the setting to be saved.
	 */
	public async toggleMembers(): Promise<void> {
		await this.toggleWidget(/^(Collapse|Expand) members$/, 'showMembers')
	}

	private async toggleWidget(label: RegExp, setting: string): Promise<void> {
		const saved = this.page.waitForResponse((response) => response.request().method() === 'PUT'
			&& response.url().includes(`/userSettings/${setting}`))
		await this.el.getByLabel(label).click()
		await saved
	}
}
