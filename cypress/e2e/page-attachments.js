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

	it('Supports inserting image into editor', function() {
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
		cy.switchToViewMode()

		cy.getEditor()
			.should('not.be.visible')
		cy.getReadOnlyEditor()
			.find('img.image__main')
			.should('be.visible')
	})

	it('Lists image in attachments sidebar and allows restore', function() {
		cy.openPage('Page1')

		cy.switchToEditMode()

		// Open attachment list
		cy.get('button.app-sidebar__toggle').click()
		cy.get('.app-sidebar-tabs__content').should('contain', 'test.png')

		// Delete image
		cy.getEditor()
			.find('[data-component="image-view"] .image__view')
			.trigger('mouseenter')
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

		// Close sidebar
		cy.get('button.app-sidebar__close').click()
	})

	it('Allows to upload, rename and delete attachments in attachments sidebar', function() {
		cy.openPage('Page1')

		// Open attachment list
		cy.get('button.app-sidebar__toggle').click()

		// Upload new attachment
		cy.intercept({ method: 'POST', url: '**/collectives/*/pages/*/attachments' }).as('attachmentUpload')
		cy.get('.upload-button')
			.find('input[type="file"]')
			.selectFile('cypress/fixtures/test.pdf', { force: true })
		cy.wait('@attachmentUpload')

		cy.get('.attachment-list').should('contain', 'test.pdf')

		// Rename attachment
		cy.get('.attachment-list')
			.contains('.list-item', 'test.pdf')
			.find('.action-item')
			.click()
		cy.get('button.action-button')
			.contains('Rename')
			.click()
		cy.intercept({ method: 'PUT', url: '**/collectives/*/pages/*/attachments/*' }).as('attachmentRename')
		cy.get('.attachment-rename-modal input')
			.clear()
		cy.get('.attachment-rename-modal input')
			.type('renamed.pdf{enter}')
		cy.wait('@attachmentRename')

		cy.get('.attachment-list').should('contain', 'renamed.pdf')

		// Delete attachment
		cy.get('.attachment-list')
			.contains('.list-item', 'renamed.pdf')
			.find('.action-item')
			.click()
		cy.intercept({ method: 'DELETE', url: '**/collectives/*/pages/*/attachments/*' }).as('attachmentDelete')
		cy.get('button.action-button')
			.contains('Delete')
			.click()
		cy.wait('@attachmentDelete')

		cy.get('.attachment-list').should('not.contain', 'renamed.pdf')

		// Close sidebar
		cy.get('button.app-sidebar__close').click({ force: true })
	})
})
