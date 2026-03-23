/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collectives trash', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Delete me')
	})

	it('Allows moving the collective to trash', function() {
		cy.visit('/apps/collectives')
		cy.openCollectiveMenu('Delete me')
		cy.clickMenuButton('Settings')
		cy.get('button')
			.contains('Delete collective')
			.click()
		cy.get('#collectives-trash')
			.click()
		cy.get('.dialog tr')
			.should('contain', 'Delete me')
	})
	it('Allows restoring the collective from trash', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.get('#collectives-trash')
			.click()
		cy.openTrashedCollectiveMenu('Delete me')
		cy.get('button').contains('Restore').click()
		cy.get('.collectives_list_item')
			.should('contain', 'Delete me')
	})

	it('Allows deleting the collective and team from trash', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')

		// Move to trash
		cy.openCollectiveMenu('Delete me')
		cy.clickMenuButton('Settings')
		cy.get('button')
			.contains('Delete collective')
			.click()
		cy.get('#collectives-trash')
			.click()
		cy.get('.dialog tr')
			.should('contain', 'Delete me')

		// Delete permanently
		cy.openTrashedCollectiveMenu('Delete me')
		cy.clickMenuButton('Delete permanently')
		cy.get('button')
			.contains('Collective and team')
			.click()
		cy.get('.dialog tr')
			.should('not.exist')
	})
})
