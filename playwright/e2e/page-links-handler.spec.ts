/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	GetCollectiveUrlParameters,
	SameTabLinkTestCaseData,
} from '../support/helpers/links.ts'

import { mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { testLinkOpensInSameTab } from '../support/helpers/links.ts'
import { randomString } from '../support/helpers/randomString.ts'

const triggers = ['preview', 'openLinkButton', 'ctrlClick'] as const

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

test.describe('Link handler: authenticated → public share URL', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const trigger of triggers) {
			test(`Opens public share URL link in same tab via ${trigger} (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				test.skip(
					trigger === 'ctrlClick' && process.env.PLAYWRIGHT_NC_SERVER_BRANCH === 'stable32',
					'ctrlClick handler not implemented on stable32',
				)

				const sourcePage = collective.getPageByTitle('Link Source')
				const targetPage = collective.getPageByTitle('Link Target')

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				const share = await collective.createShare({ page })

				const linkData: SameTabLinkTestCaseData = {
					description: 'public share URL',
					getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => targetPage.getPageUrl(share.data.token),
					getExpectedUrl: ({ baseURL, targetPage }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(), baseURL)).href,
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
					trigger,
				})

				await share.delete()
			})
		}
	}
})

test.describe('Link handler: public share → internal URL', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		for (const trigger of triggers) {
			test(`Opens internal URL link in same tab via ${trigger} in share context (${modeLabel} mode)`, async ({ baseURL, collective, editor, page, user }) => {
				test.skip(
					trigger === 'ctrlClick' && process.env.PLAYWRIGHT_NC_SERVER_BRANCH === 'stable32',
					'ctrlClick handler not implemented on stable32',
				)

				const sourcePage = collective.getPageByTitle('Link Source')
				const targetPage = collective.getPageByTitle('Link Target')

				if (!baseURL) {
					throw new Error('baseURL is not defined')
				}

				const share = await collective.createShare({ page })
				if (editMode) {
					await share.setEditable(true)
				}

				const linkData: SameTabLinkTestCaseData = {
					description: 'internal URL without share token',
					getLinkUrl: ({ targetPage }: GetCollectiveUrlParameters) => targetPage.getPageUrl(),
					getExpectedUrl: ({ baseURL, targetPage, shareToken }: GetCollectiveUrlParameters) => (new URL(targetPage.getPageUrl(shareToken), baseURL)).href,
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
					trigger,
				})

				await share.delete()
			})
		}
	}
})
