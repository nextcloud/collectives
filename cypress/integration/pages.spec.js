/**
 * @copyright Copyright (c) 2021 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
 *
 * @license GNU AGPL version 3 or any later version
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
 *  Tests for basic Page functionality.
 */

describe('Page', function() {
	before(function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.seedCollective('drei')
		cy.seedPage('vier', '', 'Readme.md')
	})

	describe('visited from collective home', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/drei')
			cy.get('.app-content-list')
				.get('.app-content-list-item')
				.should('contain', 'vier')
			cy.get('.app-content-list-item').contains('vier').click()
		})
		it('Shows the title in the enabled titleform', function() {
			cy.get('#titleform input').should('have.value', 'vier')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
		})
	})

	describe('Creating a new subpage', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/drei')
			cy.get('#text h1').should('contain', 'Welcome to your new collective')
			cy.contains('.app-content-list-item', 'vier').trigger('mouseover')
			cy.contains('.app-content-list-item', 'vier').find('button.primary').click()
			cy.get('#titleform input').should('have.value', 'New Page')
			// cy.focused().should('have.value', 'Title')
			// cy.type('This is a page with a particular long title{enter}')
		})
		it('Shows the title in the enabled titleform and full path in browser title', function() {
			// cy.get('.app-content-list-item').should('contain', 'This is a page with a particular long title')
			// cy.get('#titleform input').should('have.value', 'This is a page with a particular long title')
			// cy.get('#titleform input').should('not.have.attr', 'disabled')
			// cy.get('title').should('contain', 'vier/This is a page with a particular long title - drei - Collectives - Nextcloud')
			cy.get('title').should('contain', 'vier/New Page - drei - Collectives - Nextcloud')
		})
	})

	after(function() {
		cy.deleteCollective('drei')
	})

})
