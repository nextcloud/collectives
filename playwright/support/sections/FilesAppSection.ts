/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Locator, type Page } from '@playwright/test'
import { expect } from '@playwright/test'

export class FilesAppSection {
	public readonly fileListEl: Locator

	constructor(public readonly page: Page) {
		this.fileListEl = this.page.locator('.files-list')
	}

	public async open(): Promise<void> {
		await this.page.goto('/apps/files/')
	}

	public getFileListEntry(fileName: string): Locator {
		return this.fileListEl.locator(`[data-cy-files-list-row-name="${fileName}"]`)
	}

	public async openFile(fileName: string): Promise<void> {
		const fileEntry = this.getFileListEntry(fileName)
		await fileEntry.click()
	}

	public async hasCollectivesHeader(): Promise<void> {
		return await expect(this.fileListEl.locator('.filelist-collectives-wrapper'))
			.toContainText('The content of this folder is best viewed in the Collectives app.')
	}
}
