/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page share', function() {
	let shareUrl

	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Share me')
			.seedPage('Sharepage', '', 'Readme.md')
			.seedPage('Sharesecondpage', '', 'Readme.md')
			.seedPage('Sharesubpage', '', 'Sharepage.md')
		cy.seedPageContent('Share%20me/Sharepage/Readme.md', '## Shared page')
	})

	it('Allows sharing a page', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives', {
			onBeforeLoad(win) {
				// navigator.clipboard doesn't exist on HTTP requests (in CI), so let's create it
				if (!win.navigator.clipboard) {
					win.navigator.clipboard = {
						__proto__: {
							writeText: () => {},
						},
					}
				}
				// overwrite navigator.clipboard.writeText with cypress stub
				cy.stub(win.navigator.clipboard, 'writeText', (text) => {
					shareUrl = text
				})
					.as('clipBoardWriteText')
			},
		})
		cy.openCollective('Share me')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.intercept('POST', '**/api/v1.0/collectives/*/pages/*/shares').as('createShare')
		cy.get('.sharing-entry button.new-share-link')
			.click()
		cy.wait('@createShare')
		cy.get('.toast-success').should('contain', 'Page "Sharepage" has been shared')
		cy.get('.sharing-entry .share-select')
			.should('contain', 'View only')
		cy.get('button.sharing-entry__copy')
			.click()
		cy.get('.toast-success').should('contain', 'Link copied')
		cy.get('@clipBoardWriteText').should('have.been.calledOnce')
	})
	it('Allows opening a shared (non-editable) page', function() {
		cy.logout()
		cy.visit(shareUrl)
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Sharepage')
		cy.get('button.titleform-button').should('not.exist')
		cy.getReadOnlyEditor()
			.should('be.visible')
			.find('h2').should('contain', 'Shared page')
		cy.get('.app-content-list-item.toplevel')
			.should('contain', 'Sharepage')
		cy.get('.app-content-list-item.toplevel')
			.find('button.icon.add')
			.should('not.exist')
		cy.getEditor().should('not.exist')
	})
	it('Allows setting a page share to editable', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')
		cy.openCollective('Share me')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.get('.sharing-entry .share-select')
			.click()
		cy.intercept('PUT', '**/api/v1.0/collectives/*/pages/*/shares/*').as('updateShare')
		cy.get('.sharing-entry .share-select .dropdown-item')
			.contains('Can edit')
			.click()
		cy.wait('@updateShare')
		cy.get('.toast-success').should('contain', 'Share link of page "Sharepage" has been updated')
		cy.get('.sharing-entry .share-select')
			.should('contain', 'Can edit')
	})
	it('Allows unsharing a page', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')
		cy.openCollective('Share me')
		cy.openPage('Sharepage')
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-sharing').click()
		cy.get('.sharing-entry__actions')
			.click()
		cy.intercept('DELETE', '**/api/v1.0/collectives/*/pages/*/shares/*').as('deleteShare')
		cy.get('.unshare-button')
			.click()
		cy.wait('@deleteShare')
		cy.get('.toast-success').should('contain', 'Page "Sharepage" has been unshared')
	})
	it('Opening unshared page fails', function() {
		cy.logout()
		cy.visit(shareUrl, { failOnStatusCode: false })
		cy.get('.body-login-container').contains(/(File|Page|Share) not found/)
	})
})
