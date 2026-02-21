/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'

const collectiveName = 'LinksCollective'
const linkText = 'Link Text'
const pageTitle = 'Link Target'

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

test.describe('Page links in preview mode', () => {
	test.describe.configure({ mode: 'serial' })

	test('Opens link with slugified path to page in the same tab', async ({ collective, editor, page, user }) => {
		const sourcePage = collective.getPageByTitle('Link Source')
		const targetPage = collective.getPageByTitle('Link Target')

		await sourcePage.setLinkContent({
			linkText,
			linkUrl: `/index.php/apps/collectives/${collective.data.slug}-${collective.data.id}/${targetPage.data.slug}-${targetPage.data.id}`,
			user,
		})

		await sourcePage.open()
		await editor.openLink({
			linkText,
			pageTitle,
		})

		const expectedUrl = (new URL(targetPage.getPageUrl(), page.url())).href
		await expect(page).toHaveURL(expectedUrl)
	})

	test('Opens link with absolute path to page in the same tab', async ({ collective, editor, page, user }) => {
		const sourcePage = collective.getPageByTitle('Link Source')
		const targetPage = collective.getPageByTitle('Link Target')

		await sourcePage.setLinkContent({
			linkText,
			linkUrl: `/index.php/apps/collectives/${collectiveName}/Link%20Target`,
			user,
		})

		await sourcePage.open()
		await editor.openLink({
			linkText,
			pageTitle,
		})

		const expectedUrl = (new URL(targetPage.getPageUrl(), page.url())).href
		await expect(page).toHaveURL(expectedUrl)
	})
})
