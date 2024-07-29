/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collectives trash', function() {
	describe('move collective to trash and restore', function() {
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
			cy.get('.collectives_trash_list_item')
				.should('contain', 'Delete me')
		})
		it('Allows restoring the collective from trash', function() {
			cy.loginAs('bob')
			cy.visit('apps/collectives')
			cy.get('#collectives-trash')
				.click()
			cy.openTrashedCollectiveMenu('Delete me')
			cy.clickMenuButton('Restore')
			cy.get('.collectives_list_item')
				.should('contain', 'Delete me')
		})
	})

	describe('move collective to trash and delete permanently', function() {
		it('Allows moving the collective to trash', function() {
			cy.loginAs('bob')
			cy.visit('apps/collectives')
			cy.openCollectiveMenu('Delete me')
			cy.clickMenuButton('Settings')
			cy.get('button')
				.contains('Delete collective')
				.click()
			cy.get('#collectives-trash')
				.click()
			cy.get('.collectives_trash_list_item')
				.should('contain', 'Delete me')
		})
		it('Allows deleting the collective and team from trash', function() {
			cy.loginAs('bob')
			cy.visit('apps/collectives')
			cy.get('#collectives-trash')
				.click()
			cy.openTrashedCollectiveMenu('Delete me')
			cy.clickMenuButton('Delete permanently')
			cy.get('button')
				.contains('Collective and team')
				.click()
			cy.get('#app-navigation-vue #collectives-trash').should('not.exist')
		})
	})
})
