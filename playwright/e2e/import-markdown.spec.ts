/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'

const collectiveName = 'ImportMarkdownCollective'

const collectiveTest = createCollectiveTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{ name: collectiveName, markdownImportPath: '/var/www/html/apps/collectives/playwright/support/fixtures/files/DokuwikiMarkdownExport/pages' },
	]),
})

const test = mergeTests(collectiveTest, editorTest)

test.describe('Import Markdown', () => {
	test.describe.configure({ mode: 'serial' })

	test('attached images got rewritten and render', async ({ collective, editor }) => {
		await collective.openCollective({ pageTitle: 'start' })
		await expect(collective.page).toHaveTitle(`start - ${collectiveName} - Collectives - Nextcloud`)
		await editor.hasImage('stegosaurus.png')

		await collective.openCollective({ pageTitle: 'page1/subpage1' })
		await expect(collective.page).toHaveTitle(`subpage1 - page1 - ${collectiveName} - Collectives - Nextcloud`)
		await editor.hasImage('triceratops.png')
	})

	test('table renders', async ({ collective, editor }) => {
		await collective.openCollective({ pageTitle: 'page1' })
		await expect(collective.page).toHaveTitle(`page1 - ${collectiveName} - Collectives - Nextcloud`)
		await expect(editor.content
			.locator('table td:first-child'))
			.toHaveText('cell1')
	})

	test('internal links got rewritten and work', async ({ collective, editor }) => {
		await collective.openCollective({ pageTitle: 'start' })
		await editor.hasInternalLink('page1')
		await editor.hasInternalLink('subpage1')
	})
})
