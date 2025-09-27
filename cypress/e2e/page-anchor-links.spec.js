/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

function generateLongContent() {
	const template = '## Heading #n\n\n'
		+ 'Lorem ipsum dolor sit amet consectetur adipiscing elit. Quisque faucibus ex sapien vitae pellentesque sem placerat. In id cursus mi pretium tellus duis convallis. Tempus leo eu aenean sed diam urna tempor. Pulvinar vivamus fringilla lacus nec metus bibendum egestas. Iaculis massa nisl malesuada lacinia integer nunc posuere. Ut hendrerit semper vel class aptent taciti sociosqu. Ad litora torquent per conubia nostra inceptos himenaeos.\n\n'
	let content = ''
	for (let i = 1; i <= 10; i++) {
		content += template.replace('#n', i.toString())
	}
	return content
}

describe('Page anchor links', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Anchor Links')
			.seedPage('Page', '', 'Readme.md')
		cy.seedPageContent('Anchor Links/Page.md', generateLongContent())
	})

	beforeEach(function() {
		cy.loginAs('bob')
	})

	describe('Scrolls to heading when opening anchor link', function() {
		const heading = 'Heading 7'
		const headingAnchor = 'h-heading-7'

		it('In view mode', function() {
			cy.visit(`/apps/collectives/Anchor Links/Page#${headingAnchor}`)
			cy.getReadOnlyEditor()
				.find('h2')
				.contains(heading)
				.should('be.visible')
		})

		it('In edit mode', function() {
			cy.seedCollectivePageMode('Anchor Links', 1)
			cy.visit(`/apps/collectives/Anchor Links/Page#${headingAnchor}`)
			cy.getEditor()
				.find('h2')
				.contains(heading)
				.should('be.visible')
		})
	})
})
