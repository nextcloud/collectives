/**
 * @copyright Copyright (c) 2021 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
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
 *  Tests for basic Page functionality.
 */

describe('Page', function() {
	before(function() {
		cy.login('bob', { route: '/apps/collectives' })
		cy.deleteAndSeedCollective('Our Garden')
		cy.seedPage('Day 1', '', 'Readme.md')
		// Wait 1 second to make sure that page order by time is right
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.seedPage('Day 2', '', 'Readme.md')
		cy.seedPage('Page Title', '', 'Readme.md')
		cy.seedPage('#% special chars', '', 'Readme.md')
		cy.seedPageContent('bob', 'Our Garden/Day 2.md', 'A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day%201).')
		cy.seedPage('Template', '', 'Readme.md')
		cy.seedPageContent('bob', 'Our Garden/Template.md', 'This is going to be our template.')
	})

	beforeEach(function() {
		cy.login('bob', { route: '/apps/collectives/Our Garden' })
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	describe('visited from collective home', function() {
		it('Shows the title in the enabled titleform', function() {
			cy.get('.app-content-list-item').contains('Day 1').click()
			cy.get('#titleform input').should('have.value', 'Day 1')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
		})
	})

	describe('Sort order', function() {
		it('sorts pages by timestamp by default', function() {
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')
		})
		it('can sort pages by title and sort order is persistent', function() {
			cy.get('span.sort-clock-ascending-outline-icon').click()
			cy.get('.sort-alphabetical-ascending-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Page Title')

			cy.intercept('GET', '**/_api/*/_pages').as('getPages')
			cy.reload()
			cy.wait('@getPages')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Page Title')
			cy.get('span.sort-alphabetical-ascending-icon').click()
			cy.get('button.action-button > span.sort-clock-ascending-outline-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')
		})
	})

	describe('with special chars', function() {
		it('loads well', function() {
			cy.contains('.app-content-list-item a', '#% special chars').click()
			cy.get('.app-content-list-item').should('contain', '#% special chars')
			cy.get('#titleform input').should('have.value', '#% special chars')
		})
	})

	describe('Creating a page from template', function() {
		it('New page has template content', function() {
			// Do some handstands to ensure that new page with editor is loaded before we edit the title
			cy.intercept('POST', '**/_api/*/_pages/parent/*').as('createPage')
			cy.intercept('PUT', '**/apps/text/session/create').as('textCreateSession')
			cy.contains('.app-content-list-item', 'Our Garden')
				.find('button.icon-add')
				.click()
			cy.wait('@createPage')
			cy.wait('@textCreateSession')
			cy.get('.editor__content > .ProseMirror')
				.should('be.visible')
			cy.get('#titleform input.title')
				.should('not.have.attr', 'disabled')
			cy.get('#titleform input.title')
				.type('{selectAll}New page from Template{enter}')
			cy.get('.editor__content').contains('This is going to be our template.')
			cy.get('.app-content-list-item').eq(1)
				.should('contain', 'New page from Template')
		})
	})

	describe('Creating a new subpage', function() {
		it('Shows the title in the enabled titleform and full path in browser title', function() {
			// Do some handstands to ensure that new page with editor is loaded before we edit the title
			cy.intercept('POST', '**/_api/*/_pages/parent/*').as('createPage')
			cy.intercept('PUT', '**/apps/text/session/create').as('textCreateSession')
			cy.contains('.app-content-list-item', '#% special chars')
				.find('button.icon-add')
				.click({ force: true })
			cy.wait('@createPage')
			cy.wait('@textCreateSession')
			cy.get('.editor__content > .ProseMirror')
				.should('be.visible')
			cy.get('#titleform input.title')
				.should('not.have.attr', 'disabled')
			cy.get('#titleform input.title')
				.type('{selectAll}Subpage Title{enter}')
			cy.get('.app-content-list-item').should('contain', 'Subpage Title')
			cy.get('#titleform input').should('have.value', 'Subpage Title')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
			cy.title().should('eq', '#% special chars/Subpage Title - Our Garden - Collectives - Nextcloud')
		})
	})

	describe('Editing a page', function() {
		it('Supports page content editing and switching to read mode', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.get('#read-only-editor.editor__content > .ProseMirror').should('not.be.visible')
			cy.get('#editor > > .editor__content > .ProseMirror').should('be.visible')
				.should('have.focus')
				.type('# Heading{enter}')

			// Only run image tests on Nextcloud 24+
			if (!['22', '23'].includes(String(Cypress.env('ncVersion')))) {
				cy.log('Inserting an image')
				cy.intercept({ method: 'POST', url: '**upload' }).as('imageUpload')
				cy.get('.menubar > input[type="file"]')
					.selectFile('cypress/fixtures/test.png', { force: true })
				cy.wait('@imageUpload')
			}

			cy.log('Changing to read mode')
			cy.get('button.titleform-button')
				.click()
			cy.get('#editor > > .editor__content > .ProseMirror').should('not.be.visible')
			cy.get('#read-only-editor.editor__content > .ProseMirror').should('be.visible')
				.should('contain', 'Heading')
			if (!['22', '23'].includes(String(Cypress.env('ncVersion')))) {
				cy.get('#read-only-editor.editor__content > .ProseMirror')
					.find('img.image__main')
					.should('be.visible')
			}
		})
	})

	describe('Using the page list filter', function() {
		it('Shows only landing page and (sub)pages matching the filter string', function() {
			cy.get('input.page-filter')
				.type('Title')
			cy.get('.app-content-list-item-line-one:visible').should('have.length', 3)
			cy.get('.app-content-list-item-line-one:visible').eq(0)
				.should('contain', 'Our Garden')
			cy.get('.app-content-list-item-line-one:visible').eq(1)
				.should('contain', 'Subpage Title')
			cy.get('.app-content-list-item-line-one:visible').eq(2)
				.should('contain', 'Page Title')
		})
	})

	describe('Using the search providers', function() {
		it('Search for page and page content', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('Day 2')
			cy.get('.unified-search__results-collectives-pages').should('contain', 'Day 2')
			cy.get('.unified-search__results-collectives-page-content').should('contain', 'with Day 2 in')
		})
	})

	describe('Displaying backlinks', function() {
		it('Lists backlinks for a page', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.get('button.action-item.action-item--single.icon-menu-sidebar').click()
			cy.get('.app-sidebar-tabs__content').should('contain', 'Day 2')
		})
	})
})
