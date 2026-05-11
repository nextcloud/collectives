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
	public readonly pagePicker: Locator
	public readonly pagePickerSearch: Locator

	constructor(public readonly page: Page) {
		this.isEdit = false
		this.editor = this.page.locator('[data-cy-collectives="editor"]')
		this.reader = this.page.locator('[data-cy-collectives="reader"]')
		this.menubar = this.editor.getByRole('region')
		this.suggestionsContainer = this.page.locator('.container-suggestions')
		this.pagePicker = this.page.locator('.collectives-page-picker')
		this.pagePickerSearch = this.pagePicker.getByRole('textbox', { name: 'Search pages' })
	}

	public setMode(edit: boolean) {
		this.isEdit = edit
	}

	public async switchMode(edit: boolean): Promise<void> {
		const content = (edit ? this.editor : this.reader).locator('.ProseMirror')
		if (await content.isVisible()) {
			this.isEdit = edit
			return
		}
		await this.page.locator('.edit-button').click()
		await content.waitFor({ state: 'visible' })
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

	public async replaceContent(text: string): Promise<void> {
		await this.getContent().press('Control+a')
		await this.getContent().pressSequentially(text)
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
		await this.page.locator('.link-view-bubble')
			.waitFor({ state: 'visible' })
		return this.page.locator('.link-view-bubble')
	}

	public async hasCollectiveLink(linkText: string): Promise<void> {
		await expect((await this.getLinkBubble(linkText))
			.locator('.widgets--list .collective-page .title'))
			.toHaveText(linkText)
		// Click somewhere else to close the link bubble
		await this.getContent()
			.click()
	}

	public async openLinkViaBubblePreview({ linkText }: {
		linkText: string
	}): Promise<void> {
		const linkBubble = await this.getLinkBubble(linkText)
		await linkBubble
			.locator('.widgets--list')
			.getByRole('link')
			.click()
	}

	public async openLinkViaOpenLinkButton({ linkText }: {
		linkText: string
	}): Promise<void> {
		const linkBubble = await this.getLinkBubble(linkText)
		await linkBubble
			.getByRole('button', { name: 'Open link' })
			.click()
	}

	public async ctrlClickLink({ linkText }: {
		linkText: string
	}): Promise<void> {
		await this.getContent()
			.getByRole('link', { name: linkText, exact: true })
			.click({ modifiers: ['Control'] })
	}

	public async save(): Promise<void> {
		await this.editor.getByRole('button', { name: 'Save document' }).click()
	}

	public async openCollectiveLinkViaBubblePreview({ linkText, pageTitle }: {
		linkText: string
		pageTitle?: string
	}): Promise<void> {
		const link = await this.getLinkBubble(linkText)
		pageTitle = pageTitle || linkText
		await expect(link
			.locator('.collective-page .title'))
			.toHaveText(pageTitle)

		await link
			.getByRole('link')
			.click()
	}

	public getMentionSuggestions(): Locator {
		return this.page.getByRole('tooltip').locator('.suggestion-list')
	}
}
