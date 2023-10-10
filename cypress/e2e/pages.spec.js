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

	describe('Set page emoji', function() {
		it('Allows setting a page emoji from title bar', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.get('#titleform .page-title-icon')
				.click()
			cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '🥰').click()

			// Test persistence of changed emmoji
			cy.reload()
			cy.get('#titleform .page-title-icon')
				.should('contain', '🥰')
			cy.contains('.app-content-list-item', 'Day 1')
				.find('.app-content-list-item-icon')
				.should('contain', '🥰')

			// Unset emoji
			cy.get('#titleform .page-title-icon')
				.click()
			cy.contains('.emoji-mart-emoji.emoji-selected', '🥰').click()

			// Test persistence of unset emoji
			cy.reload()
			cy.get('#titleform .page-title-icon .emoticon-outline-icon')
			cy.contains('.app-content-list-item', 'Day 1')
				.find('.app-content-list-item-icon .collectives-page-icon')
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
				.clear()
			cy.intercept('PUT', '**/_api/*/_pages/parent/*/page/*').as('renamePage')
			cy.get('#titleform input.title')
				.type('New page from Template')
			cy.get('#titleform input.title')
				.blur()
			cy.wait('@renamePage')
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
		it('Lists attachments for the page and allows restore', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')

			// Switch to edit mode
			cy.switchPageMode(1)

			// Open attachment list
			cy.get('button.action-item .icon-menu-sidebar').click()
			cy.get('.app-sidebar-tabs__content').should('contain', 'test.png')

			// Delete image
			cy.getEditor()
				.find('[data-component="image-view"] .image__view')
				.trigger('mouseover')
			cy.getEditor()
				.get('.image__caption__delete')
				.click()
			cy.getEditor()
				.find('[data-component="image-view"] .image__view')
				.should('not.exist')

			// Restore image
			if (Cypress.env('ncVersion') !== 'stable25') {
				cy.get('.attachment-list-deleted >>>>>>> button.action-item__menutoggle')
					.click()
				cy.get('button')
					.contains('Restore')
					.click()
				cy.getEditor()
					.find('[data-component="image-view"] .image__view')
					.should('be.visible')
			}
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
				cy.get('.reference-picker input[type="text"], .reference-picker input[type="search"]')
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

	describe('Full width view', function() {
		it('Allows to toggle persistent full-width view', function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%202')
			cy.get('#titleform').should('have.css', 'max-width', '100%')
			cy.get('#read-only-editor').invoke('outerWidth').should('eq', 670)

			// Set full width mode
			cy.get('#titleform .action-item__menutoggle')
				.click()
			cy.contains('li.action', 'Full width')
				.click()
			cy.get('#titleform').should('have.css', 'max-width', 'none')
			cy.get('#read-only-editor').invoke('outerWidth').should('be.greaterThan', 700)

			// Reload to check persistence with browser storage
			cy.reload()
			cy.get('#titleform').should('have.css', 'max-width', 'none')
			cy.get('#read-only-editor').invoke('outerWidth').should('be.greaterThan', 700)

			// Unset full width mode
			cy.get('#titleform .action-item__menutoggle')
				.click()
			cy.contains('li.action', 'Full width')
				.click()
			cy.get('#titleform').should('have.css', 'max-width', '100%')
			cy.get('#read-only-editor').invoke('outerWidth').should('eq', 670)
		})
	})

	describe('Using the search providers to search for a page', function() {
		it('Search for page title', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('Day')
			cy.get('.unified-search__results-collectives-pages')
				.should('contain', 'Day 1')
		})

		it('Search for page content', function() {
			cy.get('.unified-search a').click()
			cy.get('.unified-search__form input')
				.type('share your thoughts')
			cy.get('.unified-search__results-collectives-page-content')
				.should('contain', 'your thoughts that really matter')
		})
	})

})
