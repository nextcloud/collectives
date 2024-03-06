/**
 * @copyright Copyright (c) 2022 Jonas <jonas@freesources.org>
 *
 * @author Jonas <jonas@freesources.org>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 *  Tests for Collectives trash functionality.
 */

describe('Collective', function() {
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
