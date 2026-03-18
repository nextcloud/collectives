/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

export class EditorSection {
	public isEdit: boolean
	public readonly editor: Locator
	public readonly reader: Locator
	public readonly menubar: Locator
	public readonly suggestionsContainer: Locator
	public readonly smartPicker: Locator
	public readonly smartPickerSearch: Locator

	constructor(public readonly page: Page) {
		this.isEdit = false
		this.editor = this.page.locator('[data-cy-collectives="editor"]')
		this.reader = this.page.locator('[data-cy-collectives="reader"]')
		this.menubar = this.editor.getByRole('region')
		this.suggestionsContainer = this.page.locator('.container-suggestions')
		this.smartPicker = this.page.getByRole('dialog')
		this.smartPickerSearch = this.smartPicker.getByPlaceholder('Search', { exact: true })
	}

	public setMode(edit: boolean) {
		this.isEdit = edit
	}

	public getMenu(name: string): Locator {
		return this.editor.getByRole('button', { name })
	}

	public getMenuItem(name: string): Locator {
		return this.editor.getByRole('menuitem', { name })
	}

	public async clickMenu(menu: string, item: string): Promise<void> {
		await this.getMenu(menu).click()
		await this.getMenuItem(item).click()
	}

	public getContent() {
		return (this.isEdit ? this.editor : this.reader)
			.locator('.ProseMirror')
	}

	public async hasImage(filename: string): Promise<void> {
		const srcRegex = new RegExp(`imageFileName=${filename}`)
		await expect(this.getContent()
			.locator('img'))
			.toHaveAttribute('src', srcRegex)
	}

	public async getLinkBubble(linkText: string): Promise<Locator> {
		await this.getContent()
			.getByRole('link', { name: linkText, exact: true })
			.click()
		await this.page.locator('.widgets--list')
			.waitFor({ state: 'visible' })
		return this.page.locator('.widgets--list')
	}

	public async hasCollectiveLink(linkText: string): Promise<void> {
		await expect((await this.getLinkBubble(linkText))
			.locator('.collective-page .line'))
			.toHaveText(linkText)
		// Click somewhere else to close the link bubble
		await this.getContent()
			.click()
	}

	public async openLink({ linkText }: {
		linkText: string
	}): Promise<void> {
		const link = await this.getLinkBubble(linkText)
		await link
			.getByRole('link')
			.click()
	}

	public async openCollectiveLink({ linkText, pageTitle }: {
		linkText: string
		pageTitle?: string
	}): Promise<void> {
		const link = await this.getLinkBubble(linkText)
		pageTitle = pageTitle || linkText
		await expect(link
			.locator('.collective-page .line'))
			.toHaveText(pageTitle)

		await link
			.getByRole('link')
			.click()
	}
}
