/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

export class EditorSection {
	public mode: 'reader' | 'editor'
	public readonly editorContent: Locator
	public readonly readerContent: Locator
	public readonly content: Locator

	constructor(public readonly page: Page) {
		this.mode = 'reader'
		this.editorContent = this.page.locator('[data-cy-collectives="editor"] .ProseMirror')
		this.readerContent = this.page.locator('[data-cy-collectives="reader"] .ProseMirror')
		this.content = this.mode === 'reader' ? this.readerContent : this.editorContent
	}

	public setMode(mode: 'reader' | 'editor') {
		this.mode = mode
	}

	public async hasImage(filename: string): Promise<void> {
		const srcRegex = new RegExp(`imageFileName=${filename}`)
		await expect(this.content
			.locator('img'))
			.toHaveAttribute('src', srcRegex)
	}

	public async getLinkBubble(linkText: string): Promise<Locator> {
		await this.content
			.getByRole('link', { name: linkText, exact: true })
			.click()
		await this.page.locator('.widget-custom')
			.waitFor({ state: 'visible' })
		return this.page.locator('.widget-custom')
	}

	public async hasInternalLink(linkText: string): Promise<void> {
		await expect((await this.getLinkBubble(linkText))
			.locator('.collective-page .line'))
			.toHaveText(linkText)
		// Click somewhere else to close the link bubble
		await this.content
			.click()
	}

	public async openLink({ linkText, type = 'collectivePage', pageTitle }: {
		linkText: string
		type?: 'collectivePage'
		pageTitle?: string
	}): Promise<void> {
		const link = await this.getLinkBubble(linkText)
		if (type === 'collectivePage') {
			pageTitle = pageTitle || linkText
			await expect(link
				.locator('.collective-page .line'))
				.toHaveText(pageTitle)
		}

		await link
			.getByRole('link')
			.click()
	}
}
