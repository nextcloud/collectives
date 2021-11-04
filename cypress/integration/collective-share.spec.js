/**
 * @copyright Copyright (c) 2021 Jonas <jonas@freesources.org>
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
 *  Tests for basic Collectives functionality.
 */

describe('Collective Share', function() {
	let shareUrl

	before(function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.seedCollective('Share me')
	})

	describe('collective share', function() {
		it('Allows sharing a collective', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.get('.collectives_list_item')
				.contains('li', 'Share me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Share link')
				.click().then(() => {
					cy.get('.popover__wrapper ul')
						.contains('Share link').should('not.be.visible')
					cy.get('.popover__wrapper ul')
						.contains('Copy share link').should('be.visible')
					cy.get('.popover__wrapper ul')
						.contains('Unshare').should('be.visible')
				})
			cy.get('button')
				.contains('Copy share link')
				.click().then(() => {
					cy.get('.toast-error').should('contain',
						'Could not copy link to the clipboard:'
					).then(($error) => {
						shareUrl = $error.contents()[1].textContent
					})
				})
		})
		it('Allows opening a shared collective', function() {
			cy.visit(shareUrl)
			cy.get('#titleform input').should('have.value', 'Share me')
			cy.get('#text h1').should('contain', 'Welcome to your new collective')
		})
		it('Allows unsharing a collective', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.get('.collectives_list_item')
				.contains('li', 'Share me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Unshare')
				.click().then(() => {
					cy.get('.popover__wrapper ul')
						.contains('Share link').should('be.visible')
					cy.get('.popover__wrapper ul')
						.contains('Copy share link').should('not.be.visible')
					cy.get('.popover__wrapper ul')
						.contains('Unshare').should('not.be.visible')
				})
		})
	})
})
