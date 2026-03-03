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
	collective: Collective
	targetPage: CollectivePage
}

type LinkTestCaseData = {
	description: string
	targetPageTitle?: string
	getLinkUrl: (params: GetUrlParameters) => string
	getExpectedUrl: (params: GetUrlParameters) => string
}

const sameCollectiveLinks: LinkTestCaseData[] = [
	{
		description: 'slugified collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ collective }: GetUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`,
		getExpectedUrl: ({ baseURL, collective }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified collective path URL',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ baseURL, collective }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
		getExpectedUrl: ({ baseURL, collective }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'absolute collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: () => `/index.php/apps/collectives/${collectiveName}`,
		getExpectedUrl: ({ baseURL, collective }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified page path',
		getLinkUrl: ({ collective, targetPage }: GetUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}/${targetPage.data.slug}-${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path',
		getLinkUrl: ({ targetPage }: GetUrlParameters) => `/index.php/apps/collectives/${collectiveName}/${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path URL',
		getLinkUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${collectiveName}/${encodeURIComponent(targetPage.data.title)})`, baseURL)).href,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'relative page path',
		getLinkUrl: ({ targetPage }: GetUrlParameters) => `./${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'relative Markdown file path',
		getLinkUrl: ({ targetPage }: GetUrlParameters) => `./${encodeURIComponent(targetPage.data.title)}.md`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'relative page path and fileId',
		getLinkUrl: ({ targetPage }: GetUrlParameters) => `./${encodeURIComponent(targetPage.data.title)}?fileId=${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'wrong relative page path and fileId',
		getLinkUrl: ({ targetPage }: GetUrlParameters) => `./SomePage?fileId=${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
]

const otherCollectiveLinks = [
	{
		description: 'slugified collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ collective }: GetUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`,
		getExpectedUrl: ({ baseURL, collective }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'absolute page path',
		getLinkUrl: ({ collective, targetPage }: GetUrlParameters) => `/index.php/apps/collectives/${encodeURIComponent(collective.data.name)}/${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path URL',
		getLinkUrl: ({ baseURL, collective, targetPage }: GetUrlParameters) => (new URL(`/index.php/apps/collectives/${encodeURIComponent(collective.data.name)}/${encodeURIComponent(targetPage.data.title)}?fileId=${targetPage.data.id})`, baseURL)).href,
		getExpectedUrl: ({ baseURL, targetPage }: GetUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
]

type LinkTestCase = {
	baseURL: string
	page: Page
	user: User
	editor: EditorSection
	sourcePage: CollectivePage
	targetPage: CollectivePage
	targetCollective: Collective
	linkTestCaseData: LinkTestCaseData
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
}: LinkTestCase) {
	await sourcePage.setLinkContent({
		linkText,
		linkUrl: linkTestCaseData.getLinkUrl({ baseURL, collective: targetCollective, targetPage }),
		user,
	})

	const pageTitle = linkTestCaseData.targetPageTitle ?? targetPage.data.title
	await sourcePage.open()
	await editor.openLink({
		linkText,
		pageTitle,
	})

	await expect(page).toHaveURL(linkTestCaseData.getExpectedUrl({ baseURL, collective: targetCollective, targetPage }))
}

test.describe('Page links in preview mode', () => {
	test.describe.configure({ mode: 'serial' })

	for (const linkTestCaseData of sameCollectiveLinks) {
		test(`Opens link to same collective with ${linkTestCaseData.description} in same tab`, async ({ baseURL, collective, editor, page, user }) => {
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
			})
		})
	}

	for (const linkTestCaseData of otherCollectiveLinks) {
		test(`Opens link to other collective with ${linkTestCaseData.description} in same tab`, async ({ baseURL, collective, editor, page, user }) => {
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
			})

			await trashAndDeleteCollective({ id: targetCollective.data.id, user })
		})
	}
})
