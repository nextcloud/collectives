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
		cy.seedCollective('Our Garden')
		cy.seedPage('Day 1', '', 'Readme.md')
		cy.seedPage('Day 2', '', 'Readme.md')
		cy.seedPage('#% special chars', '', 'Readme.md')
		cy.seedPageContent('bob', 'Our Garden/Day 2.md', 'A test string with Day 2 in the middle.')
	})

	describe('visited from collective home', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/Our Garden')
			cy.get('.app-content-list')
				.get('.app-content-list-item')
				.should('contain', 'Day 1')
			cy.get('.app-content-list-item').contains('Day 1').click()
		})
		it('Shows the title in the enabled titleform', function() {
			cy.get('.app-content-list-item').contains('Day 1').click()
			cy.get('#titleform input').should('have.value', 'Day 1')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
		})
	})

	describe('Sort order', function() {
		beforeEach(function() {
			cy.login('bob', 'bob', '/apps/collectives/Our Garden')
		})
		it('sorts pages by timestamp by default', function() {
			cy.get('.app-content-list-item:last-child')
				.should('contain', 'Day 1')
		})
		it('can sort pages by title', function() {
			cy.get('button.icon-access-time').click()
			cy.get('.icon-sort-by-alpha').click()
			cy.get('.app-content-list-item:last-child')
				.should('contain', 'Day 2')
		})
	})

	describe('with special chars', function() {
		it('loads well', function() {
			cy.login('bob', 'bob', '/apps/collectives/Our Garden')
			cy.contains('.app-content-list-item a', '#% special chars').click()
			cy.get('.app-content-list-item').should('contain', '#% special chars')
			cy.get('#titleform input').should('have.value', '#% special chars')
		})
	})

	describe('Creating a new subpage', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/Our Garden')
			cy.get('#text h1').should('contain', 'Welcome to your new collective')
			cy.contains('.app-content-list-item', '#% special chars')
				.find('button.icon-add')
				.click({ force: true })
			// wait for the editor to load - so it cannot steal our focus.
			// TODO: remove the next three lines once
			// https://github.com/nextcloud/text/pull/1645 landet.
			cy.get('.ProseMirror[contenteditable=true]').click()
			cy.focused().type('Some Text')
			cy.get('#titleform input').click()
			cy.focused().should('have.value', '')
			cy.focused().type('Subpage Title{enter}')
		})
		it('Shows the title in the enabled titleform and full path in browser title', function() {
			cy.get('.app-content-list-item').should('contain', 'Subpage Title')
			cy.get('#titleform input').should('have.value', 'Subpage Title')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
			cy.title().should('eq', '#% special chars/Subpage Title - Our Garden - Collectives - Nextcloud')
		})
	})

	describe('Using the search providers', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/Our Garden/Day 2')
		})
		it('Search for page and page content', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('Day 2')
			cy.get('.unified-search__results-collectives_pages').should('contain', 'Day 2')
			cy.get('.unified-search__results-collectives_pages_content').should('contain', 'with Day 2 in')
		})
	})

	after(function() {
		cy.deleteCollective('Our Garden')
	})

})
