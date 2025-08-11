/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective on mobile', function() {
	before(function() {
		cy.viewport(360, 800)
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('First Mobile Collective')
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
	})
})
