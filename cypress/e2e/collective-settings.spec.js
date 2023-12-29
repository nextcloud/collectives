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
 *  Tests for Collectives settings.
 */

describe('Collective settings', function() {

	beforeEach(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Change me')
		cy.visit('apps/collectives')
	})

	describe('set emoji', function() {
		it('Allows setting an emoji', function() {
			cy.openCollectiveMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('button.button-emoji')
				.click()
			cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '🥰').click()

			// Test persistence of changed emoji
			cy.reload()
			cy.contains('.app-navigation-entry', 'Change me')
				.find('.app-navigation-entry-icon').should('contain', '🥰')

			// Unset emoji
			cy.openCollectiveMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('button.button-emoji')
				.click()
			cy.contains('.emoji-mart-emoji.emoji-selected', '🥰').click()

			// Test persistence of unset emoji
			cy.reload()
			cy.contains('.app-navigation-entry', 'Change me')
				.find('.app-navigation-entry-icon .collectives-icon')
		})
	})

	describe('rename collective', function() {

		beforeEach(function() {
			cy.deleteCollective('Change me now')
		})

		it('Allows to rename the collective', function() {
			cy.openCollectiveMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('div.collective-name input[type="text"]').type(' now{enter}')
			cy.reload()
			cy.get('.collectives_list_item')
				.should('contain', 'Change me now')
		})

	})

	describe('change edit permissions', function() {
		it('Allows to change editing permissions', function() {
			cy.openCollectiveMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('.permissions-input-edit').contains('Admins only')
				.click()
			cy.get('div.toast-success').should('contain', 'Editing permissions updated')
		})
	})

	describe('change share permissions', function() {
		it('Allows to change sharing permissions', function() {
			cy.openCollectiveMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('.permissions-input-share').contains('Admins only')
				.click()
			cy.get('div.toast-success').should('contain', 'Sharing permissions updated')
		})
	})

	describe('change page mode', function() {
		it('Allows to change page mode', function() {
			cy.openCollectiveMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('a.navigation-list__link')
				.contains('Page settings')
				.click()
			cy.get('.edit-mode').contains('Edit')
				.click()
			cy.get('div.toast-success').should('contain', 'Default page mode updated')
		})
	})

	describe('open settings from landing page actions', function() {
		it('Allows to open settings from landing page actions', function() {
			cy.openCollective('Change me')
			cy.openPageMenu('Change me')
			cy.clickMenuButton('Settings')
			cy.get('div.permissions-input-edit')
		})
	})
})
