/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { type Locator, type Page } from '@playwright/test'
import { expect } from '@playwright/test'

export class NavigationSection {
	public readonly el: Locator
	public readonly collectivesSettingsButton: Locator
	public readonly collectivesFolderInputEl: Locator
	public readonly filePickerDialog: Locator
	public readonly filePickerBreadCrumbs: Locator
	public readonly filePickerHomeButton: Locator

	constructor(public readonly page: Page) {
		this.el = this.page.locator('#app-navigation-vue')
		this.collectivesSettingsButton = this.el.getByRole('button', { name: 'Collectives settings' })
		this.collectivesFolderInputEl = this.page.getByLabel('Collectives folder')
		this.filePickerDialog = this.page.getByRole('dialog', { name: 'Select location for collectives' })
		this.filePickerBreadCrumbs = this.filePickerDialog.locator('.breadcrumb__crumbs')
		this.filePickerHomeButton = this.filePickerBreadCrumbs.getByRole('button', { name: 'All files' })
	}

	public async open(): Promise<void> {
		await this.page.getByRole('button', { name: 'Open navigation' }).click()
		await expect(this.el).toBeVisible()
	}

	public async close(): Promise<void> {
		await this.page.getByRole('button', { name: 'Close navigation' }).click()
		await expect(this.el).not.toBeVisible()
	}

	public async openCollectivesSettings(): Promise<void> {
		await this.collectivesSettingsButton.click()
	}

	public async openUserFolderSetting(): Promise<Locator> {
		await this.openCollectivesSettings()
		await this.collectivesFolderInputEl.click()
		return this.filePickerDialog
	}

	public getFolderEntry(folderName: string): Locator {
		return this.filePickerDialog.locator(`[data-testid="file-list-row"][data-filename="${folderName}"]`)
	}

	public async createFolder(folderName: string): Promise<void> {
		await this.filePickerDialog.getByRole('button', { name: 'New' }).click()
		await this.page.getByLabel('New folder').fill(folderName)
		await this.page.getByRole('button', { name: 'Submit' }).click()
		await expect(this.filePickerBreadCrumbs.getByRole('button', { name: folderName })).toBeVisible()
	}

	public async setUserFolder(folderName: string): Promise<void> {
		await this.openUserFolderSetting()
		await this.filePickerHomeButton.click()
		const folder = this.getFolderEntry(folderName)
		if (!await folder.isVisible()) {
			await this.createFolder(folderName)
		} else {
			await folder.click()
		}

		// Click "Choose" button to confirm selection
		const chooseButton = this.filePickerDialog.getByRole('button', { name: 'Choose' })
		await expect(chooseButton).toBeVisible()
		await chooseButton.click()

		// Wait for dialog to close and settings to be saved
		await expect(this.filePickerDialog).not.toBeVisible({ timeout: 5000 })
	}
}
