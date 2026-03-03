/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'
import type { Collective } from '../support/fixtures/Collective.ts'
import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'
import type { User } from '../support/fixtures/User.ts'
import type { EditorSection } from '../support/sections/EditorSection.ts'

import { expect, mergeTests } from '@playwright/test'
import { createCollective, trashAndDeleteCollective } from '../support/fixtures/Collective.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'

const collectiveName = 'LinksCollective'

const collectiveTest = createCollectiveTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{
			name: collectiveName,
			pages: [
				{ title: 'Link Target', content: 'Some content' },
				{ title: 'Link Source' },
			],
		},
	]),
})

const test = mergeTests(collectiveTest, editorTest)

const linkText = 'Link Text'

type GetUrlParameters = {
	baseURL: string
}

type GetCollectiveUrlParameters = GetUrlParameters & {
	collective: Collective
	targetPage: CollectivePage
}

type SameTabLinkTestCaseData = {
	description: string
	targetPageTitle?: string
	getLinkUrl: (params: GetCollectiveUrlParameters) => string
	getExpectedUrl: (params: GetCollectiveUrlParameters) => string
}

type NewTabLinkTestCaseData = {
	description: string
	targetPageTitle?: string
	getLinkUrl: (params: GetUrlParameters) => string
	getExpectedUrl: (params: GetUrlParameters) => string
}

const sameCollectiveLinks: SameTabLinkTestCaseData[] = [
	{
		description: 'slugified collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ collective }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`,
		getExpectedUrl: ({ baseURL, collective }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified collective path URL',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ baseURL, collective }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
		getExpectedUrl: ({ baseURL, collective }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'absolute collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: () => `/index.php/apps/collectives/${collectiveName}`,
		getExpectedUrl: ({ baseURL, collective }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified page path',
		getLinkUrl: ({ collective, targetPage }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}/${targetPage.data.slug}-${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path',
		getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${collectiveName}/${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path URL',
		getLinkUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collectiveName}/${encodeURIComponent(targetPage.data.title)})`, baseURL)).href,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'relative page path',
		getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => `./${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'relative Markdown file path',
		getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => `./${encodeURIComponent(targetPage.data.title)}.md`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'relative page path and fileId',
		getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => `./${encodeURIComponent(targetPage.data.title)}?fileId=${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'wrong relative page path and fileId',
		getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => `./SomePage?fileId=${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
]

const otherCollectiveLinks: SameTabLinkTestCaseData[] = [
	{
		description: 'slugified collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ collective }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`,
		getExpectedUrl: ({ baseURL, collective }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'absolute page path',
		getLinkUrl: ({ collective, targetPage }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${encodeURIComponent(collective.data.name)}/${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path URL',
		getLinkUrl: ({ baseURL, collective, targetPage }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${encodeURIComponent(collective.data.name)}/${encodeURIComponent(targetPage.data.title)}?fileId=${targetPage.data.id})`, baseURL)).href,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
]

const newTabLinks: NewTabLinkTestCaseData[] = [
	{
		description: 'other Nextcloud app path',
		getLinkUrl: () => '/index.php/apps/files',
		getExpectedUrl: ({ baseURL }: GetUrlParameters) => (new URL('/index.php/apps/files/files', baseURL)).href,
	},
	{
		description: 'other Nextcloud app path URL',
		getLinkUrl: ({ baseURL }: GetUrlParameters) => (new URL('/index.php/apps/files', baseURL)).href,
		getExpectedUrl: ({ baseURL }: GetUrlParameters) => (new URL('/index.php/apps/files/files', baseURL)).href,
	},
	{
		description: 'external website URL',
		getLinkUrl: () => 'https://github.com/',
		getExpectedUrl: () => 'https://github.com/',
	},
	{
		description: 'foreign Collective URL',
		getLinkUrl: () => 'https://github.com/index.php/apps/collectives/some-collective-123/some-page-456',
		getExpectedUrl: () => 'https://github.com/index.php/apps/collectives/some-collective-123/some-page-456',
	},
]

type SameTabLinkTestCase = {
	baseURL: string
	page: Page
	user: User
	editor: EditorSection
	sourcePage: CollectivePage
	targetPage?: CollectivePage
	targetCollective?: Collective
	linkTestCaseData: SameTabLinkTestCaseData
	editMode: boolean
}

async function testLinkOpensInSameTab({
	baseURL,
	page,
	user,
	editor,
	sourcePage,
	targetPage,
	targetCollective,
	linkTestCaseData,
	editMode,
}: SameTabLinkTestCase) {
	if (!targetPage || !targetCollective) {
		throw new Error('targetPage and targetCollective must be defined for testing links opening in the same tab')
	}
	await sourcePage.setLinkContent({
		linkText,
		linkUrl: linkTestCaseData.getLinkUrl({ baseURL, collective: targetCollective, targetPage }),
		user,
	})

	const pageTitle = linkTestCaseData.targetPageTitle ?? targetPage.data.title
	await sourcePage.open()
	await sourcePage.switchMode(editMode)
	editor.setMode(editMode)
	await editor.openCollectiveLink({
		linkText,
		pageTitle,
	})

	await expect(page).toHaveURL(linkTestCaseData.getExpectedUrl({ baseURL, collective: targetCollective, targetPage }))
}

type NewTabLinkTestCase = {
	baseURL: string
	page: Page
	user: User
	editor: EditorSection
	sourcePage: CollectivePage
	targetPage?: CollectivePage
	targetCollective?: Collective
	linkTestCaseData: NewTabLinkTestCaseData
	editMode: boolean
}

async function testLinkOpensInNewTab({
	baseURL,
	page,
	user,
	editor,
	sourcePage,
	linkTestCaseData,
	editMode,
}: NewTabLinkTestCase) {
	await sourcePage.setLinkContent({
		linkText,
		linkUrl: linkTestCaseData.getLinkUrl({ baseURL }),
		user,
	})

	await sourcePage.open()
	await sourcePage.switchMode(editMode)
	editor.setMode(editMode)
	const newTabPromise = page.waitForEvent('popup')
	await editor.openLink({ linkText })
	const newTab = await newTabPromise;
	await newTab.waitForLoadState()

	await expect(newTab).toHaveURL(linkTestCaseData.getExpectedUrl({ baseURL }))
}

test.describe('Collectives links', () => {
	test.describe.configure({ mode: 'serial' })

	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkTestCaseData of sameCollectiveLinks) {
			test(`Opens link to same collective with ${linkTestCaseData.description} in same tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')
				const targetPage = collective.getPageByTitle('Link Target')

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				await testLinkOpensInSameTab({
					baseURL,
					page,
					user,
					editor,
					sourcePage,
					targetPage,
					targetCollective: collective,
					linkTestCaseData,
					editMode,
				})
			})
		}
	}

	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkTestCaseData of otherCollectiveLinks) {
			test(`Opens link to other collective with ${linkTestCaseData.description} in same tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')
				const targetCollective = await createCollective({
					name: 'Target Collective',
					user,
				})
				const targetPage = await targetCollective.createPage({ title: 'Landing page', content: '', user })

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				await testLinkOpensInSameTab({
					baseURL,
					page,
					user,
					editor,
					sourcePage,
					targetPage,
					targetCollective,
					linkTestCaseData,
					editMode,
				})

				await trashAndDeleteCollective({ id: targetCollective.data.id, user })
			})
		}
	}
})

test.describe('External links', () => {
	test.describe.configure({ mode: 'serial' })

	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkTestCaseData of newTabLinks) {
			test(`Opens link with ${linkTestCaseData.description} in new tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				await testLinkOpensInNewTab({
					baseURL,
					page,
					user,
					editor,
					sourcePage,
					linkTestCaseData,
					editMode,
				})
			})
		}
	}
})
