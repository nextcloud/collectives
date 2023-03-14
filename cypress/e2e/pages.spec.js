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
		cy.seedPageContent('Our Garden/Day 2.md', 'A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day%201).')
		cy.seedPage('Template', '', 'Readme.md')
		cy.seedPageContent('Our Garden/Template.md', 'This is going to be our template.')
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
		it('sorts pages by custom order by default', function() {
			cy.get('.app-content-list-item').eq(1)
				.should('contain', '#% special chars')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')
		})
		it('can sort pages by title/timestamp and sort order is persistent', function() {
			// Select sorting by title
			cy.get('span.sort-ascending-icon').click()
			cy.get('.sort-alphabetical-ascending-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Page Title')

			// Reload to test persistance of sort order
			cy.intercept('GET', '**/_api/*/_pages').as('getPages')
			cy.reload()
			cy.wait('@getPages')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Page Title')

			// Select sorting by timestamp
			cy.get('span.sort-alphabetical-ascending-icon').click()
			cy.get('button.action-button > span.sort-clock-ascending-outline-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')

			// Remove alternative sort order
			cy.get('span.sort-order-chip > button').click()
			cy.get('span.sort-ascending-icon')
				.should('be.visible')
			cy.get('.app-content-list-item').eq(1)
				.should('contain', '#% special chars')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')
		})
	})

	describe('Set page emoji', function() {
		it('Allows setting a page emoji from title bar', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.get('#titleform .page-title-icon')
				.click()
			cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '🥰').click()
			cy.reload()
			cy.get('#titleform .page-title-icon')
				.should('contain', '🥰')
			cy.contains('.app-content-list-item', 'Day 1')
				.find('.app-content-list-item-icon')
				.should('contain', '🥰')
		})
		it('Allows setting a page emoji from page list', function() {
			cy.contains('.app-content-list-item', 'Day 2')
				.find('.action-item__menutoggle')
				.click({ force: true })
			cy.get('button.action-button')
				.contains('Select emoji')
				.click()
			cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '😀').click()
			cy.reload()
			cy.get('#titleform .page-title-icon')
				.should('contain', '😀')
			cy.contains('.app-content-list-item', 'Day 2')
				.find('.app-content-list-item-icon')
				.should('contain', '😀')
		})
	})

	describe('Move a page using the modal', function() {
		it('Moves page to a subpage', function() {
			cy.seedPage('Move me', '', 'Readme.md')
			cy.seedPage('Target', '', 'Readme.md')
			cy.seedPage('Target Subpage', '', 'Target.md')
			cy.visit('/apps/collectives/Our%20Garden')
			cy.contains('.app-content-list-item', 'Move me')
				.find('.action-item__menutoggle')
				.click({ force: true })
			cy.get('button.action-button')
				.contains('Move page')
				.click()
			cy.get('.picker-page-list li')
				.contains('Target')
				.click()
			cy.get('.picker-move-buttons button .arrow-down-icon')
				.click()
			cy.get('.picker-buttons button')
				.contains('Move page here')
				.click()

			cy.visit('/apps/collectives/Our%20Garden/Target')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.first()
				.contains('Target Subpage')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.last()
				.contains('Move me')
		})
	})

	describe('With special chars', function() {
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
				.find('button.action-button-add')
				.click()
			cy.wait(['@createPage', '@textCreateSession'])
			cy.get('#titleform input.title')
				.should('not.have.attr', 'disabled')
			cy.get('#titleform input.title')
				.type('{selectAll}New page from Template{enter}')
			cy.getEditor(Cypress.config('defaultCommandTimeout') * 2)
				.should('be.visible')
				.contains('This is going to be our template.')
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
				.find('button.action-button-add')
				.click({ force: true })
			cy.wait(['@createPage', '@textCreateSession'])
			cy.getEditor()
				.should('be.visible')
			cy.get('#titleform input.title')
				.should('not.have.attr', 'disabled')
			cy.get('#titleform input.title')
				.type('{selectAll}Subpage Title{enter}')
			cy.get('.app-content-list-item').should('contain', 'Subpage Title')
			cy.get('#titleform input').should('have.value', 'Subpage Title')
			cy.get('#titleform input').should('not.have.attr', 'disabled')
			cy.title().should('eq', 'Subpage Title - #% special chars - Our Garden - Collectives - Nextcloud')
		})
	})

	describe('Editing a page', function() {
		it('Supports page content editing and switching to read mode', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')

			cy.log('Inserting an image')
			cy.intercept({ method: 'POST', url: '**/text/attachment/upload*' }).as('attachmentUpload')
			cy.get('input[data-text-el="attachment-file-input"]')
				.selectFile('cypress/fixtures/test.png', { force: true })
			cy.wait('@attachmentUpload')

			cy.log('Inserting a heading')
			cy.getEditor()
				.should('be.visible')
				.type('## Heading{enter}')
				.focus()

			cy.log('Inserting a user mention')
			// Wait 1 second to prevent race condition with previous insertion
			cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
			cy.getEditor()
				.should('be.visible')
				.type('@admi')
			cy.get('.tippy-content')
				.contains('admin')
				.click()

			// Wait 1 second to prevent race condition when switching mode
			cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting

			// Switch back to view mode
			cy.switchPageMode(0)

			cy.getEditor()
				.should('not.be.visible')
			cy.getReadOnlyEditor()
				.find('img.image__main')
				.should('be.visible')
			cy.getReadOnlyEditor()
				.should('be.visible')
				.should('contain', 'Heading')
			cy.getReadOnlyEditor()
				.find('.mention')
				.should('contain', 'admin')
		})
		it('Lists attachments for the page', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.get('button.action-item .icon-menu-sidebar').click()
			cy.get('.app-sidebar-tabs__content').should('contain', 'test.png')
		})
	})

	// Reference picker autocompletion is only available on Nextcloud 26+
	if (Cypress.env('ncVersion') !== 'stable25') {
		describe('Using the reference picker', function() {
			it('Supports selecting a page from a collective', function() {
				cy.visit('/apps/collectives/Our%20Garden/Page%20Title')

				cy.getEditor()
					.should('be.visible')
					.type('/Coll')
				cy.get('.tippy-content .link-picker__item')
					.contains('Collective pages')
					.click()
				cy.get('.reference-picker input[type="text"]')
					.type('Day 2')
				cy.get('.search-result')
					.contains('Day 2')
					.click()

				/*
				 * Disable for now - in CI Nextcloud is on http (no TLS) and link previews don't get rendered
				cy.getEditor()
					.get('.widgets--list .collective-page--info .line')
					.should('contain', 'Day 2')
				 */
			})
		})
	}

	describe('Using the page list filter', function() {
		it('Shows only landing page and (sub)pages matching the filter string', function() {
			cy.get('input[name="pageFilter"]')
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

	describe('Changing page mode', function() {
		it('Opens edit mode per default', function() {
			cy.seedCollectivePageMode('Our Garden', 1)
			cy.visit('/apps/collectives/Our%20Garden/Day%202')
			cy.getEditor()
				.should('be.visible')
			cy.getReadOnlyEditor()
				.should('not.be.visible')
		})

		it('Opens view mode per default', function() {
			cy.seedCollectivePageMode('Our Garden', 0)
			cy.visit('/apps/collectives/Our%20Garden/Day%202')
			cy.getReadOnlyEditor()
				.should('be.visible')
			cy.getEditor()
				.should('not.be.visible')
		})
	})

	describe('Display table of contents', function() {
		it('Allows to display/close TOC and switch page modes in between', function() {
			cy.seedPage('TableOfContents', '', 'Readme.md')
			cy.seedPageContent('Our Garden/TableOfContents.md', '## Second-Level Heading')
			cy.visit('/apps/collectives/Our%20Garden/TableOfContents')
			cy.get('#titleform .action-item__menutoggle')
				.click()

			cy.log('Show outline in view mode')
			cy.contains('button', 'Show outline')
				.click()
			cy.get('#text-container .editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			// Switch to edit mode
			cy.switchPageMode(1)

			cy.get('.text-editor .editor--toc .editor--toc__item')
				.should('contain', 'Second-Level Heading')

			cy.log('Close outline in edit mode')
			cy.get('.text-editor .editor--outline__header .close-icon')
				.click()

			// Switch back to view mode
			cy.switchPageMode(0)

			cy.get('.editor--toc')
				.should('not.exist')
		})
	})

	describe('Using the search providers to search for a page', function() {
		it('Search for page and page content', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('Day')
			cy.get('.unified-search__results-collectives-pages')
				.should('contain', 'Day 1')
		})
	})

	describe('Using the search providers to search page content', function() {
		it('Search for page and page content', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('share your thoughts')
			cy.get('.unified-search__results-collectives-page-content')
				.should('contain', 'your thoughts that really matter. Whether it')
		})
	})

	describe('Displaying backlinks', function() {
		it('Lists backlinks for a page', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.get('button.action-item .icon-menu-sidebar').click()
			cy.get('a#backlinks').click()
			cy.get('.app-sidebar-tabs__content').should('contain', 'Day 2')
		})
	})

	describe('Print view', function() {
		it('renders all the pages', function() {
			let printStub
			cy.visit('/apps/collectives/_/print/Our%20Garden', {
				onBeforeLoad: (win) => {
					printStub = cy.stub(win, 'print').as('print')
				},
			})
			cy.get('main')
				.should('contain', 'Preparing collective for exporting or printing')
			cy.get('#page-title-collective').should('contain', 'Our Garden')
			cy.get('main').should('contain', 'Day 2')
			cy.get('@print').should('be.called')
				.then(() => {
					printStub.restore()
				})
		})
	})
})
