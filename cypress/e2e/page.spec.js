/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden').as('garden')
			.seedPage('Day 1', '', 'Readme.md')
		// Wait 1 second to make sure that page order by time is right
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.then(() => this.garden)
			.seedPage('Day 2', '', 'Readme.md')
			.seedPage('#% special chars', '', 'Readme.md')
		cy.seedPageContent('Our Garden/Day 2.md', 'A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day%201).')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Our Garden')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	it('Allows setting a page emoji from title bar', function() {
		cy.openPage('Day 1')
		cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
			.click()
		cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '🥰').click()

		// Test persistence of changed emoji
		cy.reload()
		cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
			.should('contain', '🥰')
		cy.contains('.app-content-list-item', 'Day 1')
			.find('.app-content-list-item-icon')
			.should('contain', '🥰')

		// Unset emoji
		cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
			.click()
		cy.contains('.emoji-mart-emoji.emoji-selected', '🥰').click()

		// Test persistence of unset emoji
		cy.reload()
		cy.get('[data-cy-collectives="page-title-container"] .page-title-icon .emoticon-outline-icon')
		cy.contains('.app-content-list-item', 'Day 1')
			.find('.app-content-list-item-icon .collectives-page-icon')
	})
	it('Allows setting a page emoji from page list', function() {
		cy.contains('.app-content-list-item', 'Day 2')
			.find('.action-item__menutoggle')
			.click({ force: true })
		cy.get('button.action-button')
			.contains('Select emoji')
			.click()
		cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '😀').click()
		cy.reload()
		cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
			.should('contain', '😀')
		cy.contains('.app-content-list-item', 'Day 2')
			.find('.app-content-list-item-icon')
			.should('contain', '😀')
	})

	it('Title with special chars loads well', function() {
		cy.contains('.app-content-list-item a', '#% special chars').click()
		cy.get('.app-content-list-item').should('contain', '#% special chars')
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', '#% special chars')
	})

	it('Subpage: shows the title in the enabled titleform and full path in browser title', function() {
		// Do some handstands to ensure that new page with editor is loaded before we edit the title
		cy.intercept('POST', '**/api/v1.0/collectives/*/pages/*').as('createPage')
		cy.intercept('PUT', '**/apps/text/session/*/create').as('textCreateSession')
		cy.contains('.app-content-list-item', '#% special chars')
			.find('button.action-button-add')
			.click({ force: true })
		cy.wait(['@createPage', '@textCreateSession'])
		cy.getEditor()
			.should('be.visible')
		cy.get('[data-cy-collectives="page-title-container"] input.title')
			.should('not.have.attr', 'disabled')
		cy.get('[data-cy-collectives="page-title-container"] input.title')
			.type('{selectAll}Subpage Title{enter}')
		cy.get('.app-content-list-item').should('contain', 'Subpage Title')
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Subpage Title')
		cy.get('[data-cy-collectives="page-title-container"] input').should('not.have.attr', 'disabled')
		cy.title().should('eq', 'Subpage Title - #% special chars - Our Garden - Collectives - Nextcloud')
	})

	it('Allows to toggle persistent full-width view', function() {
		cy.openPage('Day 2')
		cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'sheet-view')
		cy.getReadOnlyEditor()
			.find('.editor__content')
			.invoke('outerWidth')
			.should('be.lessThan', 800)

		// Set full width mode
		cy.get('[data-cy-collectives="page-title-container"] .action-item__menutoggle')
			.click()
		cy.contains('li.action', 'Full width')
			.click()
		cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'full-width-view')
		cy.getReadOnlyEditor()
			.find('.editor__content')
			.invoke('outerWidth')
			.should('be.greaterThan', 800)

		// Reload to check persistence
		cy.reload()
		cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'full-width-view')
		cy.getReadOnlyEditor()
			.find('.editor__content')
			.invoke('outerWidth')
			.should('be.greaterThan', 800)

		// Unset full width mode
		cy.get('[data-cy-collectives="page-title-container"] .action-item__menutoggle')
			.click()
		cy.contains('li.action', 'Full width')
			.click()
		cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'sheet-view')
		cy.getReadOnlyEditor()
			.find('.editor__content')
			.invoke('outerWidth')
			.should('be.lessThan', 800)
	})
})
