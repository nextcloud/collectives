/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Collective } from '../support/fixtures/Collective.ts'
import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
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
type getUrlParameters = {
	baseURL: string
	collective: Collective
	targetPage: CollectivePage
}

const sameWindowCollectiveLinks = [
	{
		description: 'slugified collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ collective }: getUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`,
		getExpectedUrl: ({ baseURL, collective }: getUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified collective path URL',
		targetPageTitle: 'Landing page',
		getLinkUrl: ({ baseURL, collective }: getUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
		getExpectedUrl: ({ baseURL, collective }: getUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'absolute collective path',
		targetPageTitle: 'Landing page',
		getLinkUrl: () => `/index.php/apps/collectives/${collectiveName}`,
		getExpectedUrl: ({ baseURL, collective }: getUrlParameters) => (new URL(`/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}`, baseURL)).href,
	},
	{
		description: 'slugified page path',
		getLinkUrl: ({ collective, targetPage }: getUrlParameters) => `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}/${targetPage.data.slug}-${targetPage.data.id}`,
		getExpectedUrl: ({ baseURL, targetPage }: getUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path',
		getLinkUrl: ({ targetPage }: getUrlParameters) => `/index.php/apps/collectives/${collectiveName}/${encodeURIComponent(targetPage.data.title)}`,
		getExpectedUrl: ({ baseURL, targetPage }: getUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
	{
		description: 'absolute page path URL',
		getLinkUrl: ({ baseURL, targetPage }: getUrlParameters) => (new URL(`/index.php/apps/collectives/${collectiveName}/${targetPage.data.title})`, baseURL)).href,
		getExpectedUrl: ({ baseURL, targetPage }: getUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
	},
]

test.describe('Page links in preview mode', () => {
	test.describe.configure({ mode: 'serial' })

	for (const { description, targetPageTitle, getLinkUrl, getExpectedUrl } of sameWindowCollectiveLinks) {
		test(`Opens link with ${description} in same tab`, async ({ baseURL, collective, editor, page, user }) => {
			const sourcePage = collective.getPageByTitle('Link Source')
			const targetPage = collective.getPageByTitle('Link Target')

			if (!baseURL) {
				throw new Error('baseURL is not defined')
			}

			await sourcePage.setLinkContent({
				linkText,
				linkUrl: getLinkUrl({ baseURL, collective, targetPage }),
				user,
			})

			const pageTitle = targetPageTitle ?? targetPage.data.title
			await sourcePage.open()
			await editor.openLink({
				linkText,
				pageTitle,
			})

			await expect(page).toHaveURL(getExpectedUrl({ baseURL, collective, targetPage }))
		})
	}
})
