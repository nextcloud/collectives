/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

export class TitleBarSection {
	public readonly el: Locator
	public readonly title: Locator

	constructor(public readonly page: Page) {
		this.el = this.page.locator('.page-title-container')
		this.title = this.el.getByRole('textbox')
	}

	public async clickActionMenu(action: string): Promise<void> {
		await this.el.getByRole('button', { name: 'Actions' })
			.click()
		await this.page.getByRole('menuitem', { name: action, exact: true })
			.click()
	}
}
