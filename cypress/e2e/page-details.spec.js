/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page details', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden')
			.seedPage('Day 1', '', 'Readme.md')
			.seedPage('TableOfContents', '', 'Readme.md')
		cy.seedPageContent('Our Garden/Day 2.md', 'A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day%201).')
		cy.seedPageContent('Our Garden/TableOfContents.md', '## Second-Level Heading')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Our Garden')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	describe('Display table of contents', function() {
		it('Allows to display/close TOC and switch page modes in between', function() {
			cy.openPage('TableOfContents')
			cy.get('#titleform .action-item__menutoggle')
				.click()

			cy.log('Show outline in view mode')
			cy.contains('button', 'Show outline')
				.click()
			cy.getReadOnlyEditor()
				.find('.editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			cy.switchToEditMode()

			cy.getEditor()
				.find('.editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			cy.log('Close outline in edit mode')
			cy.getEditor()
				.find('.editor--outline__header .close-icon')
				.click()

			// Switch back to view mode
			cy.switchToViewMode()

			cy.get('.editor--toc')
				.should('not.exist')
		})
	})

	describe('Displaying backlinks', function() {
		it('Lists backlinks for a page', function() {
			if (['stable27'].includes(Cypress.env('ncVersion'))) {
				cy.intercept('PUT', '**/apps/text/session/create').as('textCreateSession')
			} else {
				cy.intercept('PUT', '**/apps/text/session/*/create').as('textCreateSession')
			}
			cy.openPage('Day 1')
			cy.wait('@textCreateSession')
			cy.get('button.app-sidebar__toggle').click()
			cy.get('#tab-button-backlinks').click()
			cy.get('.app-sidebar-tabs__content').should('contain', 'Day 2')
		})
	})
})
