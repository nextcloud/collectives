/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Pages', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden').as('garden')
			.seedPage('Day 1', '', 'Readme.md')
		// Wait 1 second to make sure that page order by time is right
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.then(() => this.garden)
			.seedPage('Day 2', '', 'Readme.md')
			.seedPage('Page Title', '', 'Readme.md')
			.seedPage('#% special chars', '', 'Readme.md')
		cy.seedPageContent('Our Garden/Day 2.md', 'A test string with Day 2 in the middle and a [link to Day 1](/index.php/apps/collectives/Our%20Garden/Day%201).')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Our Garden')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	describe('visited from collective home', function() {
		it('Shows the title in the enabled titleform', function() {
			cy.get('.app-content-list-item').contains('Day 1').click()
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Day 1')
			cy.get('[data-cy-collectives="page-title-container"] input').should('not.have.attr', 'disabled')
		})
	})

	describe('Set page emoji', function() {
		it('Allows setting a page emoji from title bar', function() {
			cy.openPage('Day 1')
			cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
				.click()
			cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '🥰').click()

			// Test persistence of changed emmoji
			cy.reload()
			cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
				.should('contain', '🥰')
			cy.contains('.app-content-list-item', 'Day 1')
				.find('.app-content-list-item-icon')
				.should('contain', '🥰')

			// Unset emoji
			cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
				.click()
			cy.contains('.emoji-mart-emoji.emoji-selected', '🥰').click()

			// Test persistence of unset emoji
			cy.reload()
			cy.get('[data-cy-collectives="page-title-container"] .page-title-icon .emoticon-outline-icon')
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
			cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
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
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', '#% special chars')
		})
	})

	describe('Creating a new subpage', function() {
		it('Shows the title in the enabled titleform and full path in browser title', function() {
			// Do some handstands to ensure that new page with editor is loaded before we edit the title
			cy.intercept('POST', '**/api/v1.0/collectives/*/pages/*').as('createPage')
			cy.intercept('PUT', '**/apps/text/session/*/create').as('textCreateSession')
			cy.contains('.app-content-list-item', '#% special chars')
				.find('button.action-button-add')
				.click({ force: true })
			cy.wait(['@createPage', '@textCreateSession'])
			cy.getEditor()
				.should('be.visible')
			cy.get('[data-cy-collectives="page-title-container"] input.title')
				.should('not.have.attr', 'disabled')
			cy.get('[data-cy-collectives="page-title-container"] input.title')
				.type('{selectAll}Subpage Title{enter}')
			cy.get('.app-content-list-item').should('contain', 'Subpage Title')
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Subpage Title')
			cy.get('[data-cy-collectives="page-title-container"] input').should('not.have.attr', 'disabled')
			cy.title().should('eq', 'Subpage Title - #% special chars - Our Garden - Collectives - Nextcloud')
		})
	})

	describe('Editing a page', function() {
		it('Supports page content editing and switching to read mode', function() {
			cy.openPage('Day 1')

			cy.log('Inserting an image')
			cy.intercept({ method: 'POST', url: '**/text/attachment/upload*' }).as('attachmentUpload')
			cy.getEditor()
				.should('be.visible')
				.find('input[data-text-el="attachment-file-input"]')
				.selectFile('cypress/fixtures/test.png', { force: true })
			cy.wait('@attachmentUpload')

			cy.log('Inserting a heading')
			// Wait 1 second to prevent race condition with previous insertion
			cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
			cy.getEditorContent(true)
				.type('## Heading{enter}')

			cy.log('Inserting a user mention')
			// Wait 1 second to prevent race condition with previous insertion
			cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
			cy.getEditorContent(true)
				.type('@admi')
			cy.get('.tippy-content')
				.contains('admin')
				.click()

			// Wait 1 second to prevent race condition when switching mode
			cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting

			// Switch back to view mode
			cy.switchToViewMode()

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
			cy.openPage('Day 1')

			cy.switchToEditMode()

			// Open attachment list
			cy.get('button.app-sidebar__toggle').click()
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
			cy.get('.attachment-list-deleted button.action-item__menutoggle')
				.click()
			cy.get('button')
				.contains('Restore')
				.click()
			cy.getEditor()
				.find('[data-component="image-view"] .image__view')
				.should('be.visible')
		})
	})

	describe('Using the reference picker', function() {
		it('Supports selecting a page from a collective', function() {
			cy.openPage('Page Title')

			cy.getEditorContent(true)
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

	describe('Full width view', function() {
		it('Allows to toggle persistent full-width view', function() {
			cy.openPage('Day 2')
			cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'sheet-view')
			cy.getReadOnlyEditor()
				.find('.editor__content')
				.invoke('outerWidth')
				.should('be.lessThan', 800)

			// Set full width mode
			cy.get('[data-cy-collectives="page-title-container"] .action-item__menutoggle')
				.click()
			cy.contains('li.action', 'Full width')
				.click()
			cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'full-width-view')
			cy.getReadOnlyEditor()
				.find('.editor__content')
				.invoke('outerWidth')
				.should('be.greaterThan', 800)

			// Reload to check persistence
			cy.reload()
			cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'full-width-view')
			cy.getReadOnlyEditor()
				.find('.editor__content')
				.invoke('outerWidth')
				.should('be.greaterThan', 800)

			// Unset full width mode
			cy.get('[data-cy-collectives="page-title-container"] .action-item__menutoggle')
				.click()
			cy.contains('li.action', 'Full width')
				.click()
			cy.get('[data-cy-collectives="page-title-container"]').should('have.class', 'sheet-view')
			cy.getReadOnlyEditor()
				.find('.editor__content')
				.invoke('outerWidth')
				.should('be.lessThan', 800)
		})
	})

	describe('Using the search providers to search for a page', function() {
		it('Search for page title', function() {
			cy.get('.unified-search a, button.unified-search__button, .unified-search-menu button').click()
			cy.get('.unified-search__form input, .unified-search-modal input')
				.type('Day')
			cy.get('.unified-search__results-collectives-pages, .unified-search-modal__results')
				.should('contain', 'Day 1')
		})

		it('Search for page content', function() {
			cy.get('.unified-search a, button.unified-search__button, .unified-search-menu button').click()
			cy.get('.unified-search__form input, .unified-search-modal input')
				.type('share your thoughts')
			cy.get('.unified-search__results-collectives-page-content, .unified-search-modal__results')
				.should('contain', 'your thoughts that really matter')
		})
	})

	describe('Search dialog', () => {
		beforeEach(() => {
			cy.get('input[name="pageFilter"]').type('collective')
			cy.get('.search-dialog-container', { timeout: 5000 })
				.should('be.visible')
				.as('searchDialog')
		})

		it('Shows search dialog', () => {
			cy.get('.search-dialog__info')
				.invoke('text')
				.invoke('trim')
				.should('equal', 'Found 5 matches for "collective"')
		})

		it('Clears search', () => {
			cy.get('.search-dialog__buttons')
				.find('button[aria-label="Clear search"]')
				.click()
			cy.get('@searchDialog').should('not.exist')
		})

		it('Toggles highlight all', () => {
			cy.get('.search-dialog__highlight-all')
				.find('span.checkbox-radio-switch-checkbox')
				.click()

			cy.get('.search-dialog__info')
				.invoke('text')
				.invoke('trim')
				.should('equal', 'Match 1 of 5 for "collective"')
		})

		it('Moves to next search', () => {
			cy.get('.search-dialog__buttons')
				.find('button[aria-label="Find next match"]')
				.click()

			cy.get('.search-dialog__info')
				.invoke('text')
				.invoke('trim')
				.should('equal', 'Match 2 of 5 for "collective"')
		})

		it('Moves to previous search', () => {
			cy.get('.search-dialog__buttons')
				.find('button[aria-label="Find previous match"]')
				.click()

			cy.get('.search-dialog__info')
				.invoke('text')
				.invoke('trim')
				.should('equal', 'Match 5 of 5 for "collective"')
		})
	})
})
