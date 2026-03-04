/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { ViewerLinkTestCaseData } from '../support/helpers/links.ts'

import { mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { testLinkOpensInViewer } from '../support/helpers/links.ts'
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

const links: ViewerLinkTestCaseData[] = [
	{
		description: 'absolute files app path to image file',
		getLinkUrl: ({ fileId }) => `/index.php/f/${fileId}`,
		fixtureName: 'test.md',
		mimetype: 'text/markdown',
		getPath: () => '',
	},
	{
		description: 'absolute files app path to text file',
		getLinkUrl: ({ fileId }) => `/index.php/f/${fileId}`,
		fixtureName: 'test.png',
		mimetype: 'image/png',
		getPath: () => '',
	},
	{
		description: 'absolute files app path to PDF file',
		getLinkUrl: ({ fileId }) => `/index.php/f/${fileId}`,
		fixtureName: 'test.pdf',
		mimetype: 'application/pdf',
		getPath: ({ sourcePage }) => `${sourcePage.data.collectivePath}`,
	},
]

test.describe('Links to viewer', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const linkData of links) {
			test(`Opens link with ${linkData.description} in viewer (${modeLabel} mode)`, async ({ collective, editor, page, user }) => {
				const sourcePage = collective.getPageByTitle('Link Source')

				const fileId = await user.uploadFixture({
					name: linkData.fixtureName,
					path: linkData.getPath({ sourcePage }),
					mimetype: linkData.mimetype,
				}, page)

				await testLinkOpensInViewer({
					page,
					user,
					editor,
					sourcePage,
					linkData,
					fileId,
					editMode,
				})
			})
		}
	}
})
