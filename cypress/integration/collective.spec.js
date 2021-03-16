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
 *  Tests for basic Collectives functionality.
 */

describe('Collective', function() {
	before(function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.seedCollective('Preexisting Collective')
	})

	// Note: the different assertions in here
	// all happen without any page reload or navigation.
	//
	// Cookies are cleared after every test.
	// So in all but the first run it block
	// bob will be logged out.
	describe('after creation', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective('Created just now')
		})
		it('Shows the name in the disabled titleform', function() {
			cy.get('#titleform input').should('have.value', ' Created just now')
			cy.get('#titleform input').should('have.attr', 'disabled')
		})
		it('Has an initial Readme.md', function() {
			cy.get('#text h1').should('contain', 'Welcome to your new collective')
		})
		it('Allows creation of pages', function() {
			cy.get('.app-content-list button.primary')
				.should('contain', 'Add a page')
		})
	})
	describe('in the files app', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/files')
		})
		it('has a matching folder', function() {
			cy.get('#fileList').should('contain', 'Collectives')
			cy.get('#fileList a').contains('Collectives').click()
			cy.get('#controls .breadcrumb').should('contain', 'Collectives')
			cy.get('#fileList').should('contain', 'Preexisting Collective')
			cy.get('#fileList a').contains('Preexisting Collective').click()
			cy.get('#controls .breadcrumb').should('contain', 'Preexisting Collective')
			cy.get('#fileList').should('contain', 'Readme.md')
		})
	})
	describe('in the circles app', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/circles')
		})
		it('has a matching circle', function() {
			cy.get('.circle .title').should('contain', 'Preexisting Collective')
			cy.get('.circle .title').contains('Preexisting Collective').click()
			cy.get('#memberslist .username').should('contain', 'bob')
		})
	})
})
