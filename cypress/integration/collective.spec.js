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
	describe('name conflicts', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.seedCollective('Preexisting Collective')
		})
		it('Reports existing circle', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective('Preexisting Collective')
			cy.get('main .empty-content').should('contain', 'No collective selected')
			cy.get('.toast-warning').should('contain', 'Could not create the collective')
			cy.get('.toast-warning').should('contain', 'A circle with that name exists')
		})
	})

	// Note: the different assertions in here
	// all happen without any page reload or navigation.
	//
	// Cookies are cleared after every test.
	// So in all but the first run it block
	// bob will be logged out.
	describe('after creation', function() {
		const name = 'Created just now ' + Math.random().toString(36).substr(2, 4)
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective(name)
		})
		it('Shows the name in the disabled titleform', function() {
			cy.get('#titleform input').should('have.value', ` ${name}`)
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

	describe('reloading works', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/Preexisting%20Collective')
			cy.get('#titleform input').should('have.value', ' Preexisting Collective')
		})
		it('Shows the name in the disabled titleform', function() {
			cy.reload()
			cy.get('#titleform input').should('have.value', ' Preexisting Collective')
		})
	})

	describe('move collective to trash and restore', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective('Delete me')
		})
		it('Allows moving the collective to trash', function() {
			cy.get('.app-navigation-toggle')
				.click()
			cy.get('.collectives_list_item')
				.contains('li', 'Delete me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Move to trash')
				.click()
			cy.get('.collectives_list_item')
				.should('not.contain', 'Delete me')
			cy.get('.settings-button')
				.click()
			cy.get('.collectives_trash_list_item')
				.should('contain', 'Delete me')
		})
		it('Allows restoring the collective from trash', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.get('.settings-button')
				.click()
			cy.get('.collectives_trash_list_item')
				.contains('li', 'Delete me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Restore collective')
				.click()
			cy.get('.collectives_list_item')
				.should('contain', 'Delete me')
		})
	})

	describe('move collective to trash and delete permanently', function() {
		it('Allows moving the collective to trash', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.get('.collectives_list_item')
				.contains('li', 'Delete me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Move to trash')
				.click()
			cy.get('.collectives_list_item')
				.should('not.contain', 'Delete me')
			cy.get('.settings-button')
				.click()
			cy.get('.collectives_trash_list_item')
				.should('contain', 'Delete me')
		})
		it('Allows deleting the collective and circle from trash', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.get('.settings-button')
				.click()
			cy.get('.collectives_trash_list_item')
				.contains('li', 'Delete me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Permanently delete collective and circle')
				.click()
			cy.get('.collectives_list_item')
				.should('not.contain', 'Delete me')
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
