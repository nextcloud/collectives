/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page edit', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden').as('garden')
			.seedPage('Day 1', '', 'Readme.md')
		// Wait 1 second to make sure that page order by time is right
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.then(() => this.garden)
			.seedPage('Day 2', '', 'Readme.md')
			.seedPage('Page Title', '', 'Readme.md')
		cy.seedPageContent('Our Garden/Day 2.md', 'A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day%201).')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Our Garden')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	it('Supports page content editing and switching to read mode', function() {
		cy.openPage('Day 1')

		cy.log('Inserting a heading')
		// Wait 1 second to prevent race condition with previous insertion
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.getEditorContent(true)
			.type('## Heading{enter}')

		cy.log('Inserting a user mention')
		// Wait 1 second to prevent race condition with previous insertion
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.getEditorContent(true)
			.type('@admi')
		cy.get('.tippy-content')
			.contains('admin')
			.click()

		// Wait 1 second to prevent race condition when switching mode
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting

		// Switch back to preview mode
		cy.switchToPreviewMode()

		cy.getEditor()
			.should('not.be.visible')
		cy.getReadOnlyEditor()
			.should('be.visible')
			.should('contain', 'Heading')
		cy.getReadOnlyEditor()
			.find('.mention')
			.should('contain', 'admin')
	})

	it('Supports selecting a page from a collective', function() {
		cy.openPage('Page Title')

		cy.getEditorContent(true)
			.type('/Coll')
		cy.get('.tippy-content .link-picker__item')
			.contains('Collective pages')
			.click()
		cy.get('.reference-picker input[type="text"], .reference-picker input[type="search"]')
			.type('Day 2')
		cy.get('.search-result')
			.contains('Day 2')
			.click()

		/*
		 * Disable for now - in CI Nextcloud is on http (no TLS) and link previews don't get rendered
		cy.getEditor()
			.get('.widgets--list .collective-page--info .line')
			.should('contain', 'Day 2')
		 */
	})
})
