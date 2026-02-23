/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page share with password protection', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('SharePasswordCollective')
			.seedPage('Sharepage', '', 'Readme.md')
		cy.seedPageContent('SharePasswordCollective/Sharepage.md', '## Shared page')
	})

	it('Allows sharing a page', function() {
		cy.loginAs('bob')
		cy.stubClipboardAndVisit('/apps/collectives')
		cy.openCollective('SharePasswordCollective')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.intercept('POST', '**/api/v1.0/collectives/*/pages/*/shares').as('createShare')
		cy.get('.sharing-entry button.new-share-link')
			.click()
		cy.wait('@createShare')
		cy.get('.toast-success').should('contain', 'Page "Sharepage" has been shared')
		cy.get('.sharing-entry .share-select')
			.should('contain', 'View only')
		cy.get('button.sharing-entry__copy')
			.click()
		cy.get('.toast-success').should('contain', 'Link copied')
		cy.getClipboardText().as('shareUrl')
	})
	it('Allows adding password protection to a page share', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')
		cy.openCollective('SharePasswordCollective')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.get('.sharing-entry__actions').click()
		cy.get('button').contains('Advanced settings').click()
		cy.get('.sharing-entry__settings').contains('Set password').click()
		cy.get('.sharing-entry__settings input[type="password"]').type('{selectAll}password')

		cy.intercept('PUT', '**/api/v1.0/collectives/*/pages/*/shares/*').as('updateShare')
		cy.get('.sharing-entry__settings button').contains('Update share').click()
		cy.wait('@updateShare')
		cy.get('.toast-success').should('contain', 'Share link of page "Sharepage" has been updated')
	})
	it('Allows opening a password-protected shared (non-editable) page', function() {
		cy.logout()
		cy.visit(this.shareUrl)
		cy.get('input[type="password"]').type('password')
		if (['stable32'].includes(Cypress.env('ncVersion'))) {
			cy.get('#password-input-form input[type="submit"]').click()
		} else {
			cy.get('button').contains('Submit').click()
		}

		cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Sharepage')
		cy.getReadOnlyEditor()
			.should('be.visible')
			.find('h2').should('contain', 'Shared page')
		cy.get('.app-content-list-item.toplevel')
			.should('contain', 'Sharepage')
	})
})
