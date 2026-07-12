/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectiveTest } from '../support/fixtures/create-collectives.ts'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { randomString } from '../support/helpers/randomString.ts'

const heading = 'Heading 7'
const headingAnchor = 'h-heading-7'

function generateLongContent() {
	let content = `[Jump to anchor only ${heading}](#${headingAnchor})\n\n`
		+ `[Jump to full URL ${heading}](#${headingAnchor})\n\n`
	const template = '## Heading #n\n\n'
		+ 'Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.\n\n'
	for (let i = 1; i <= 10; i++) {
		content += template.replace('#n', i.toString())
	}
	return content
}

const collectiveTest = createCollectiveTest.extend({
	// eslint-disable-next-line no-empty-pattern
	collectiveConfigs: async ({}, use) => use([
		{
			name: randomString(),
			pages: [{ title: 'Anchor Page' }],
		},
	]),
})

const test = mergeTests(collectiveTest, editorTest)

test.describe('Page anchor links', () => {
	for (const editMode of [false, true]) {
		const modeLabel = editMode ? 'edit' : 'preview'
		let p: CollectivePage

		test.beforeEach(async ({ collective, user, page }) => {
			p = collective.getPageByTitle('Anchor Page')
			await p.setContent({ content: generateLongContent(), user, page })
		})

		test(`Scrolls to heading on initial load (${modeLabel} mode)`, async ({ editor, page }) => {
			// navigate directly with the fragment
			await page.goto(`${p.getPageUrl()}#${headingAnchor}`)
			await p.switchMode(editMode)
			editor.setMode(editMode)

			await expect(editor.getContent().getByRole('heading', { name: heading, exact: true })).toBeInViewport()
		})

		test(`Scrolls to heading when clicking in-page anchor link (${modeLabel} mode)`, async ({ editor, page }) => {
			await p.open(false)
			await p.switchMode(editMode)
			editor.setMode(editMode)

			const anchorOnlyLink = editor.getContent()
				.getByRole('link', { name: `Jump to anchor only ${heading}`, exact: true })
			const fullUrlLink = editor.getContent()
				.getByRole('link', { name: `Jump to full URL ${heading}`, exact: true })

			for (const link of [anchorOnlyLink, fullUrlLink]) {
				await link.scrollIntoViewIfNeeded()
				await link.click()

				await expect(page).toHaveURL(new RegExp(`#${headingAnchor}$`))
				await expect(editor.getContent().getByRole('heading', { name: heading, exact: true })).toBeInViewport()
			}
		})
	}
})
