/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page share with enforced password protection', function() {
	before(function() {
		cy.loginAs('admin')
		cy.enableApp('password_policy')
		cy.setAppConfig('core', 'shareapi_enable_link_password_by_default', 'yes')
		cy.setAppConfig('core', 'shareapi_enforce_links_password', 'yes')
		cy.logout()

		cy.loginAs('bob')
		cy.deleteAndSeedCollective('SharePasswordEnforcedCollective')
			.seedPage('Sharepage', '', 'Readme.md')
	})

	after(function() {
		cy.loginAs('admin')
		cy.setAppConfig('core', 'shareapi_enable_link_password_by_default', 'no')
		cy.setAppConfig('core', 'shareapi_enforce_links_password', 'no')
		cy.disableApp('password_policy')
		cy.logout()
	})

	it('Fails to share a page with weak password with enforced password protection and password policy', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')
		cy.openCollective('SharePasswordEnforcedCollective')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.intercept('GET', '**/apps/password_policy/api/v1/generate').as('generatePassword')
		cy.get('.sharing-entry button.new-share-link')
			.click()
		cy.wait('@generatePassword')
		cy.get('input[autocomplete="new-password"]').type('{selectAll}password12')
		cy.intercept('POST', '**/api/v1.0/collectives/*/pages/*/shares').as('createShare')
		cy.get('button').contains('Create share').click()
		cy.wait('@createShare')
		cy.get('.toast-error').should('contain', 'Failed to share page "Sharepage": Password is among the 1,000,000 most common ones.')
	})
	it('Allows to share a page with enforced password protection and password policy', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')
		cy.openCollective('SharePasswordEnforcedCollective')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.intercept('GET', '**/apps/password_policy/api/v1/generate').as('generatePassword')
		cy.get('.sharing-entry button.new-share-link')
			.click()
		cy.wait('@generatePassword')
		cy.intercept('POST', '**/api/v1.0/collectives/*/pages/*/shares').as('createShare')
		cy.get('button').contains('Create share').click()
		cy.wait('@createShare')
		cy.get('.toast-success').should('contain', 'Page "Sharepage" has been shared')

		cy.get('.sharing-entry__actions').click()
		cy.get('button').contains('Advanced settings').click()
		cy.get('.sharing-entry__settings input[type="checkbox"]')
			.should('be.checked')
			.and('have.attr', 'disabled')
	})
})
