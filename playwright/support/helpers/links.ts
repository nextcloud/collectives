/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { Collective } from '../fixtures/Collective.ts'
import type { CollectivePage } from '../fixtures/CollectivePage.ts'
import type { User } from '../fixtures/User.ts'
import type { EditorSection } from '../sections/EditorSection.ts'

import { expect } from '@playwright/test'

export type GetUrlParameters = {
	baseURL: string
}

export type GetCollectiveUrlParameters = GetUrlParameters & {
	collective: Collective
	targetPage: CollectivePage
}

export type ViewerLinkTestCaseData = {
	description: string
	getLinkUrl: (params: { fileId: number }) => string
	fixtureName: string
	mimetype: string
	getPath: (params: { sourcePage: CollectivePage }) => string
}

export type SameTabLinkTestCaseData = {
	description: string
	targetPageTitle?: string
	getLinkUrl: (params: GetCollectiveUrlParameters) => string
	getExpectedUrl: (params: GetCollectiveUrlParameters) => string
}

export type NewTabLinkTestCaseData = {
	description: string
	getLinkUrl: (params: GetUrlParameters) => string
	getExpectedUrl: (params: GetUrlParameters) => string
}

export type ViewerLinkTestCase = {
	page: Page
	user: User
	editor: EditorSection
	sourcePage: CollectivePage
	linkData: ViewerLinkTestCaseData
	fileId: number
	editMode: boolean
}

export type SameTabLinkTestCase = {
	baseURL: string
	page: Page
	user: User
	editor: EditorSection
	sourcePage: CollectivePage
	targetPage?: CollectivePage
	targetCollective?: Collective
	linkData: SameTabLinkTestCaseData
	editMode: boolean
}

export type NewTabLinkTestCase = {
	baseURL: string
	page: Page
	user: User
	editor: EditorSection
	sourcePage: CollectivePage
	targetPage?: CollectivePage
	targetCollective?: Collective
	linkData: NewTabLinkTestCaseData
	editMode: boolean
}

/**
 * Test that a link opens a file in Viewer.
 *
 * @param options the options
 * @param options.page Playwright page object
 * @param options.user user performing the action
 * @param options.editor editor section object to interact with the editor
 * @param options.sourcePage the source page
 * @param options.linkData test case data
 * @param options.fileId fileId of uploaded fixture file
 * @param options.editMode whether to test in edit mode or preview mode
 */
export async function testLinkOpensInViewer({
	page,
	user,
	editor,
	sourcePage,
	linkData,
	fileId,
	editMode,
}: ViewerLinkTestCase) {
	const linkText = 'Link Text'

	await sourcePage.setLinkContent({
		linkText,
		linkUrl: linkData.getLinkUrl({ fileId }),
		user,
		page,
	})

	await sourcePage.open()
	await sourcePage.switchMode(editMode)
	editor.setMode(editMode)
	await editor.openLink({ linkText })
	await expect(sourcePage.getViewerContent()
		.locator('.modal-header'))
		.toContainText(linkData.fixtureName)

	let selector = ''
	if (linkData.mimetype.startsWith('image/')) {
		selector = 'img'
	} else if (linkData.mimetype.startsWith('text/')) {
		selector = '[data-text-el="editor-container"]'
	} else if (linkData.mimetype === 'application/pdf') {
		selector = 'iframe'
	}
	await expect(sourcePage.getViewerContent()
		.locator(selector))
		.toBeVisible()
}

/**
 * Test that a link opens in same tab.
 *
 * @param options the options
 * @param options.baseURL base URL of the Nextcloud instance
 * @param options.page Playwright page object
 * @param options.user user performing the action
 * @param options.editor editor section object to interact with the editor
 * @param options.sourcePage the source page
 * @param options.targetPage the target page
 * @param options.targetCollective the target collective
 * @param options.linkData test case data
 * @param options.editMode whether to test in edit mode or preview mode
 */
export async function testLinkOpensInSameTab({
	baseURL,
	page,
	user,
	editor,
	sourcePage,
	targetPage,
	targetCollective,
	linkData,
	editMode,
}: SameTabLinkTestCase) {
	const linkText = 'Link Text'
	if (!targetPage || !targetCollective) {
		throw new Error('targetPage and targetCollective must be defined for testing links opening in the same tab')
	}
	await sourcePage.setLinkContent({
		linkText,
		linkUrl: linkData.getLinkUrl({ baseURL, collective: targetCollective, targetPage }),
		user,
		page,
	})

	const pageTitle = linkData.targetPageTitle ?? targetPage.data.title
	await sourcePage.open()
	await sourcePage.switchMode(editMode)
	editor.setMode(editMode)
	await editor.openCollectiveLink({
		linkText,
		pageTitle,
	})

	await expect(page).toHaveURL(linkData.getExpectedUrl({ baseURL, collective: targetCollective, targetPage }))
}

/**
 * Test that a link opens in new tab.
 *
 * @param options the options
 * @param options.baseURL base URL of the Nextcloud instance
 * @param options.page Playwright page object
 * @param options.user user performing the action
 * @param options.editor editor section object to interact with the editor
 * @param options.sourcePage the source page
 * @param options.linkData test case data
 * @param options.editMode whether to test in edit mode or preview mode
 */
export async function testLinkOpensInNewTab({
	baseURL,
	page,
	user,
	editor,
	sourcePage,
	linkData,
	editMode,
}: NewTabLinkTestCase) {
	const linkText = 'Link Text'

	await sourcePage.setLinkContent({
		linkText,
		linkUrl: linkData.getLinkUrl({ baseURL }),
		user,
		page,
	})

	await sourcePage.open()
	await sourcePage.switchMode(editMode)
	editor.setMode(editMode)
	const newTabPromise = page.waitForEvent('popup')
	await editor.openLink({ linkText })
	const newTab = await newTabPromise
	await newTab.waitForLoadState()

	await expect(newTab).toHaveURL(linkData.getExpectedUrl({ baseURL }))
}
