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
		cy.contains('.page-list-item .app-navigation-entry-link', 'Day 1')
	})

	it('Allows to display/close TOC and switch page modes in between', function() {
		const tocSelector = '.editor__toc .toc-list__item'
		cy.openPage('TableOfContents')
		cy.getReadOnlyEditor()
			.contains('Second-Level Heading')
		cy.wait(200) // eslint-disable-line cypress/no-unnecessary-waiting

		cy.openPageTitleMenu()
		cy.clickMenuButton('Show outline')
		cy.getReadOnlyEditor()
			.find(tocSelector)
			.should('contain', 'Second-Level Heading')
		cy.getReadOnlyEditor()
			.find('.pin-outline-icon')
			.click()

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

		cy.get('.editor__toc')
			.should('not.exist')

		// Switch back to preview mode
		cy.switchToPreviewMode()
			.contains('Second-Level Heading')

		cy.get('.editor__toc')
			.should('not.exist')
	})

	it('Allows to download markdown file', function() {
		cy.intercept('PUT', '**/apps/text/session/**/create').as('textCreateSession')
		cy.openPage('Day 1')
		cy.wait('@textCreateSession')

		cy.openPageTitleMenu()
		cy.clickMenuButton('Download')

		cy.readFile(`${Cypress.config('downloadsFolder')}/Day 1.md`)
	})

	it('Lists backlinks for a page', function() {
		cy.intercept('PUT', '**/apps/text/session/**/create').as('textCreateSession')
		cy.openPage('Day 1')
		cy.wait('@textCreateSession')
		cy.get('button.page-sidebar-button').click()
		cy.get('#tab-button-backlinks').click()
		cy.get('.app-sidebar-tabs__content').should('contain', 'Day 2')
	})
})
