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
			.seedPage('Day 1', '', 'Readme.md').then(({ collectiveId, pageId }) => {
				cy.seedPageContent('Our Garden/Day 2.md', `A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our-Garden-${collectiveId}/Day-1-${pageId}).`)
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
			// TODO: Remove once we only support nc33+
			const tocSelector = ['stable30', 'stable31', 'stable32'].includes(Cypress.env('ncVersion'))
				? '.editor--toc .editor--toc__item'
				: '.editor__toc .toc-list__item'
			cy.openPage('TableOfContents')
			cy.getReadOnlyEditor()
				.contains('Second-Level Heading')
			cy.wait(200) // eslint-disable-line cypress/no-unnecessary-waiting

			cy.openPageTitleMenu()
			cy.clickMenuButton('Show outline')
			cy.getReadOnlyEditor()
				.find(tocSelector)
				.should('contain', 'Second-Level Heading')
			// TODO Remove condition once we only support nc33+
			if (!['stable30', 'stable31', 'stable32'].includes(Cypress.env('ncVersion'))) {
				cy.getReadOnlyEditor()
					.find('.editor__toc .pin-outline-icon')
					.click()
			}

			// Reload to test persistence of toc
			cy.reload()
			cy.getReadOnlyEditor()
				.find(tocSelector)
				.should('contain', 'Second-Level Heading')

			cy.switchToEditMode()
			cy.getEditor()
				.contains('Second-Level Heading')
			cy.wait(200) // eslint-disable-line cypress/no-unnecessary-waiting

			cy.getEditor()
				.find(tocSelector)
				.should('contain', 'Second-Level Heading')

			cy.log('Close toc in edit mode')
			// TODO Remove condition once we only support nc33+
			if (['stable30', 'stable31', 'stable32'].includes(Cypress.env('ncVersion'))) {
				cy.getEditor()
					.find('.editor--outline__header .close-icon')
					.click()
			} else {
				// TODO: Fix bug in text that toc is not pinned after reload
				cy.getEditor()
					.find('.editor__toc .pin-outline-icon')
					.click()

				cy.getEditor()
					.find('.editor__toc .close-icon')
					.click()

				// Click outside toc to trigger `mouseleave`
				cy.getEditor()
					.click()
			}

			// Switch back to view mode
			cy.switchToViewMode()
				.contains('Second-Level Heading')

			// TODO: Remove first selector once we only support nc33+
			cy.get('.editor--toc, .editor__toc')
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
