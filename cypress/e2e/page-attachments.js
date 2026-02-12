/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page attachments', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Attachments Collective')
			.seedPage('Page1', '', 'Readme.md')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Attachments Collective')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Page1')
	})

	it('Inserted attachment listed in sidebar tab', function() {
		cy.openPage('Page1')

		cy.intercept({ method: 'POST', url: '**/text/attachment/upload*' }).as('textAttachmentUpload')
		cy.getEditor()
			.should('be.visible')
			.find('input[data-text-el="attachment-file-input"]')
			.selectFile('cypress/fixtures/test.png', { force: true })
		cy.wait('@textAttachmentUpload')

		// Wait 1 second to prevent race condition when switching mode
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting

		// Switch back to view mode
		cy.switchToPreviewMode()

		cy.getEditor()
			.should('not.be.visible')
		cy.getReadOnlyEditor()
			.find('img.image__main')
			.should('be.visible')

		// Open attachment list
		cy.get('button.app-sidebar__toggle').click()

		if (['stable31'].includes(Cypress.env('ncVersion'))) {
			cy.get('.attachment-list-not-embedded').should('contain', 'test.png')
		} else {
			cy.get('.attachment-list-embedded').should('contain', 'test.png')
			cy.get('.attachment-list-not-embedded').should('not.exist')
		}

		// Close sidebar
		cy.get('button.app-sidebar__close').click({ force: true })
	})

	it('Removing attachment from editor lists it in non-embedded list in sidebar tab', function() {
		cy.openPage('Page1')

		cy.switchToEditMode()

		// Open attachment list
		cy.get('button.app-sidebar__toggle').click()

		if (['stable31'].includes(Cypress.env('ncVersion'))) {
			cy.get('.attachment-list-not-embedded').should('contain', 'test.png')
		} else {
			cy.get('.attachment-list-embedded').should('contain', 'test.png')
			cy.get('.attachment-list-not-embedded').should('not.exist')
		}

		// Delete image from editor
		cy.getEditor()
			.find('[data-component="image-view"] .image__view')
			.trigger('mouseenter')
		cy.getEditor()
			.get('.image__caption__delete')
			.click()
		cy.getEditor()
			.find('[data-component="image-view"] .image__view')
			.should('not.exist')

		cy.get('.attachment-list-not-embedded').should('contain', 'test.png')
		cy.get('.attachment-list-embedded').should('not.exist')

		// Delete attachment
		cy.get('.attachment-list-not-embedded')
			.contains('.list-item', 'test.png')
			.find('.action-item button')
			.click({ force: true })
		cy.intercept({ method: 'DELETE', url: '**/collectives/*/pages/*/attachments/*' }).as('attachmentDelete')
		cy.get('button.action-button')
			.contains('Delete')
			.click()
		cy.wait('@attachmentDelete')

		// Close sidebar
		cy.get('button.app-sidebar__close').click({ force: true })
	})

	it('Allows to upload, rename, delete and restore attachment in sidebar tab', function() {
		cy.openPage('Page1')

		// Open attachment list
		cy.get('button.app-sidebar__toggle').click()

		// Upload new attachment
		cy.intercept({ method: 'POST', url: '**/collectives/*/pages/*/attachments' }).as('attachmentUpload')
		cy.get('.upload-area')
			.find('input[type="file"]')
			.selectFile('cypress/fixtures/test.pdf', { force: true })
		cy.wait('@attachmentUpload')

		cy.get('.attachment-list-not-embedded').should('contain', 'test.pdf')

		// Rename attachment
		cy.get('.attachment-list-not-embedded')
			.contains('.list-item', 'test.pdf')
			.find('.action-item button')
			.click({ force: true })
		cy.get('button.action-button')
			.contains('Rename')
			.click()
		cy.intercept({ method: 'PUT', url: '**/collectives/*/pages/*/attachments/*' }).as('attachmentRename')
		cy.get('.attachment-rename-modal input')
			.clear()
		cy.get('.attachment-rename-modal input')
			.type('renamed.pdf{enter}')
		cy.wait('@attachmentRename')

		cy.get('.attachment-list-not-embedded').should('contain', 'renamed.pdf')

		// Delete attachment
		cy.get('.attachment-list-not-embedded')
			.contains('.list-item', 'renamed.pdf')
			.find('.action-item button')
			.click({ force: true })
		cy.intercept({ method: 'DELETE', url: '**/collectives/*/pages/*/attachments/*' }).as('attachmentDelete')
		cy.get('button.action-button')
			.contains('Delete')
			.click()
		cy.wait('@attachmentDelete')

		cy.get('.attachment-list-not-embedded').should('not.exist')
		cy.get('.attachment-list-deleted').should('contain', 'renamed.pdf')

		// Restore attachment
		cy.get('.attachment-list-deleted')
			.contains('.list-item', 'renamed.pdf')
			.find('.action-item button')
			.click({ force: true })
		cy.get('button.action-button')
			.contains('Restore')
			.click()

		cy.get('.attachment-list-not-embedded').should('contain', 'renamed.pdf')
		cy.get('.attachment-list-deleted').should('not.exist')

		// Close sidebar
		cy.get('button.app-sidebar__close').click({ force: true })
	})
})
