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
		cy.seedPage('fuenf', '', 'Readme.md')
		cy.seedPageContent('bob', 'drei/fuenf.md', 'A test string with fuenf in the middle.')
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
			cy.contains('.app-content-list-item', 'vier').find('button.icon-add').click({ force: true })
			cy.focused().should('have.value', '')
			cy.focused().type('Subpage Title{enter}')
		})
		it('Shows the title in the enabled titleform and full path in browser title', function() {
			cy.get('.app-content-list-item').should('contain', 'Subpage Title')
			cy.get('#titleform input').should('have.value', 'Subpage Title')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
			cy.title().should('eq', 'vier/Subpage Title - drei - Collectives - Nextcloud')
		})
	})

	describe('Using the search providers', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/drei/fuenf')
		})
		it('Search for page and page content', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('fuenf')
			cy.get('.unified-search__results-collectives_pages').should('contain', 'fuenf')
			cy.get('.unified-search__results-collectives_pages_content').should('contain', 'with fuenf in')
		})
	})

	after(function() {
		cy.deleteCollective('drei')
	})

})
