/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page templates', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Template Collective')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.intercept('GET', '**/api/v1.0/collectives/*/pages/templates').as('getTemplates')
		cy.visit('apps/collectives/Template Collective')
		cy.wait('@getTemplates')
	})

	it('Create a new template', function() {
		cy.openCollectiveMenu('Template Collective')
		cy.clickMenuButton('Manage templates')

		// Create template
		cy.get('[data-cy-collectives="templates-dialog"] button')
			.contains('Add a template')
			.click()

		cy.get('#viewer .ProseMirror')
			.should('be.visible')
			.type('## Template Heading{enter}Template body')

		cy.get('#viewer button.header-close')
			.click()

		// Rename template
		cy.contains('.template-list-item', 'Template')
			.find('.template-action-buttons')
			.click()

		cy.clickMenuButton('Rename')
		cy.get('.template-list-item input[type="text"]')
			.type('{selectAll}Supertemplate{enter}')

		// Set emoji
		cy.contains('.template-list-item', 'Supertemplate')
			.find('.template-list-item-icon')
			.click()

		cy.contains('.emoji-mart-scroll .emoji-mart-emoji', '🥰')
			.click()

		cy.contains('.template-list-item', 'Supertemplate')
			.find('.template-list-item-icon')
			.should('contain', '🥰')

		cy.get('[data-cy-collectives="templates-dialog"] button.modal-container__close')
			.click()
	})

	it('Create a new page from template', function() {
		cy.get('.page-list-headerbar button[aria-label="Add a page"]')
			.click()

		cy.get('.template-item')
			.contains('Supertemplate')
			.click()
		cy.getEditor()
			.should('be.visible')
			.contains('Template body')

		cy.get('[data-cy-collectives="page-title-container"] .page-title-icon')
			.should('contain', '🥰')
	})

	it('Create a new blank page', function() {
		cy.get('.page-list-headerbar button[aria-label="Add a page"]')
			.click()

		cy.get('.template-item')
			.contains('Blank page')
			.click()
		cy.getEditor()
			.should('be.visible')
			.should('not.contain', 'Template body')
	})

	it('Delete template', function() {
		cy.openCollectiveMenu('Template Collective')
		cy.clickMenuButton('Manage templates')

		cy.contains('.template-list-item', 'Supertemplate')
			.find('.template-action-buttons')
			.click()

		cy.clickMenuButton('Delete')

		cy.get('.template-list-item')
			.should('not.exist')

		cy.get('[data-cy-collectives="templates-dialog"] button.modal-container__close')
			.click()
	})
})
