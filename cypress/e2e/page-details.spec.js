/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page details', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden')
			.seedPage('TableOfContents', '', 'Readme.md')
			.seedPage('Day 2', '', 'Readme.md')
			.seedPage('Day 1', '', 'Readme.md').then(({ pageId }) => {
				cy.seedPageContent('Our Garden/Day 2.md', `A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day-1-${pageId}).`)
			})
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
			cy.getReadOnlyEditor()
				.contains('Second-Level Heading')
			cy.wait(200) // eslint-disable-line cypress/no-unnecessary-waiting

			cy.openPageTitleMenu()
			cy.clickMenuButton('Show outline')
			cy.getReadOnlyEditor()
				.find('.editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			// Reload to test persistence of outline
			cy.reload()
			cy.getReadOnlyEditor()
				.find('.editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			cy.switchToEditMode()
			cy.getEditor()
				.contains('Second-Level Heading')
			cy.wait(200) // eslint-disable-line cypress/no-unnecessary-waiting

			cy.getEditor()
				.find('.editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			cy.log('Close outline in edit mode')
			cy.getEditor()
				.find('.editor--outline__header .close-icon')
				.click()

			// Switch back to view mode
			cy.switchToViewMode()
				.contains('Second-Level Heading')

			cy.get('.editor--toc')
				.should('not.exist')
		})
	})

	describe('Download markdown file', function() {
		it('Allows to download markdown file', function() {
			cy.intercept('PUT', '**/apps/text/session/*/create').as('textCreateSession')
			cy.openPage('Day 1')
			cy.wait('@textCreateSession')

			cy.openPageTitleMenu()
			cy.clickMenuButton('Download')

			cy.readFile(`${Cypress.config('downloadsFolder')}/Day 1.md`)
		})
	})

	describe('Displaying backlinks', function() {
		it('Lists backlinks for a page', function() {
			cy.intercept('PUT', '**/apps/text/session/*/create').as('textCreateSession')
			cy.openPage('Day 1')
			cy.wait('@textCreateSession')
			cy.get('button.app-sidebar__toggle').click()
			cy.get('#tab-button-backlinks').click()
			cy.get('.app-sidebar-tabs__content').should('contain', 'Day 2')
		})
	})
})
