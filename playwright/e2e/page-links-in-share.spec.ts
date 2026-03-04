/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { GetCollectiveUrlParameters, NewTabLinkTestCaseData, SameTabLinkTestCaseData } from '../support/helpers/links.ts'

import { mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { testLinkOpensInNewTab, testLinkOpensInSameTab } from '../support/helpers/links.ts'
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

const sameTabLinks: SameTabLinkTestCaseData[] = [
	{
		description: 'slugified page path',
		getLinkUrl: ({ targetPage, shareToken }: GetCollectiveUrlParameters) => targetPage.getPageUrl(shareToken),
		getExpectedUrl: ({ baseURL, targetPage, shareToken }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(shareToken), baseURL)).href,
	},
]

const newTabLinks: NewTabLinkTestCaseData[] = [
	{
		description: 'external website URL',
		getLinkUrl: () => 'https://github.com/',
		getExpectedUrl: () => 'https://github.com/',
	},
]

test.describe('Collectives links in share', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkData of sameTabLinks) {
			test(`Opens link with ${linkData.description} in same tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')
				const targetPage = collective.getPageByTitle('Link Target')

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				const share = await collective.createShare({ page })
				if (editMode) {
					await share.setEditable(true)
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
					shareToken: share.data.token,
				})

				await share.delete()
			})
		}
	}
})

test.describe('External links in share', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkData of newTabLinks) {
			test(`Opens link with ${linkData.description} in new tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				const share = await collective.createShare({ page })
				if (editMode) {
					await share.setEditable(true)
				}

				await testLinkOpensInNewTab({
					baseURL,
					page,
					user,
					editor,
					sourcePage,
					linkData,
					editMode,
					shareToken: share.data.token,
				})

				await share.delete()
			})
		}
	}
})
