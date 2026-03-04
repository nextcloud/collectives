/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	GetUrlParameters,
	NewTabLinkTestCaseData,
} from '../support/helpers/links.ts'

import { mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { testLinkOpensInNewTab } from '../support/helpers/links.ts'
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

const links: NewTabLinkTestCaseData[] = [
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

test.describe('External links', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkData of links) {
			test(`Opens link with ${linkData.description} in new tab (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
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
					linkData,
					editMode,
				})
			})
		}
	}
})
