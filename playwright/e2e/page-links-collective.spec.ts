/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	GetCollectiveUrlParameters,
	SameTabLinkTestCaseData,
} from '../support/helpers/links.ts'

import { mergeTests } from '@playwright/test'
import { createCollective, trashAndDeleteCollective } from '../support/fixtures/Collective.ts'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { testLinkOpensInSameTab } from '../support/helpers/links.ts'
import { randomString } from '../support/helpers/randomString.ts'

const collectiveTest = createCollectiveTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{
			name: randomString(),
			pages: [
				{ title: 'Link Target', content: 'Some content' },
				{ title: 'Link Source' },
			],
		},
	]),
})

const test = mergeTests(collectiveTest, editorTest)

const links: SameTabLinkTestCaseData[] = [
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
		getLinkUrl: ({ collective }) => `/index.php/apps/collectives/${collective.data.name}`,
		getExpectedUrl: ({ baseURL, collective }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified page path',
		getLinkUrl: ({ collective, targetPage }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}/${targetPage.data.slug}-${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path',
		getLinkUrl: ({ collective, targetPage }: GetCollectiveUrlParameters) => `/index.php/apps/collectives/${collective.data.name}/${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path URL',
		getLinkUrl: ({ baseURL, collective, targetPage }: GetCollectiveUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.name}/${encodeURIComponent(targetPage.data.title)})`, baseURL)).href,
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

test.describe('Collectives links', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkData of links) {
			test(`Opens link to same collective with ${linkData.description} in same tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
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
					linkData,
					editMode,
				})
			})
		}
	}

	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkData of otherCollectiveLinks) {
			test(`Opens link to other collective with ${linkData.description} in same tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')
				const targetCollective = await createCollective({
					name: randomString(),
					page,
				})
				const targetPage = await targetCollective.createPage({ title: 'Landing page', content: '', user, page })

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
					linkData,
					editMode,
				})

				await trashAndDeleteCollective({ id: targetCollective.data.id, page })
			})
		}
	}
})
