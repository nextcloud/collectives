/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective on mobile', function() {
	before(function() {
		cy.viewport(360, 800)
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('First Mobile Collective')
			.seedPage('Page 1', '', 'Readme.md')
		cy.deleteAndSeedCollective('Second Mobile Collective')
	})

	beforeEach(function() {
		cy.loginAs('bob')
	})

	after(function() {
		cy.viewport(1280, 900)
	})

	describe('Open app with mobile viewport', function() {
		it('Opens collectives navigation', function() {
			cy.visit('/apps/collectives')

			cy.get('.collectives_list_item')
				.contains('li', 'First Mobile Collective')
				.should('be.visible')
		})

		it('Allows to navigate pages', function() {
			cy.viewport(360, 800)
			cy.visit('/apps/collectives/First Mobile Collective')
			cy.get('.app-content-list').should('not.be.visible')

			cy.get('.app-details-toggle').click()
			cy.get('.app-content-list').should('be.visible')

			cy.openPage('Page 1')
			cy.get('.app-content-list').should('not.be.visible')
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Page 1')
		})
	})
})
