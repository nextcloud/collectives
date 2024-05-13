/**
 * @copyright Copyright (c) 2021 Jonas <jonas@freesources.org>
 *
 * @author Jonas <jonas@freesources.org>
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

describe('Collective Share', function() {
	let shareUrl

	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Share me')
	})

	describe('collective share', function() {
		it('Allows sharing a collective', function() {
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
			cy.openCollectiveMenu('Share me')
			cy.clickMenuButton('Share link')
			cy.intercept('POST', '**/_api/*/share').as('createShare')
			cy.get('.sharing-entry button.new-share-link')
				.click()
			cy.wait('@createShare')
			cy.get('.toast-success').should('contain', 'Collective "Share me" has been shared')
			cy.get('.sharing-entry .share-select')
				.should('contain', 'View only')
			cy.get('button.sharing-entry__copy')
				.click()
			cy.get('.toast-success').should('contain', 'Link copied')
			cy.get('@clipBoardWriteText').should('have.been.calledOnce')
		})
		it('Allows opening a shared (non-editable) collective', function() {
			cy.logout()
			cy.visit(shareUrl)
			cy.get('#titleform input').should('have.value', 'Share me')
			cy.get('button.titleform-button').should('not.exist')
			cy.getReadOnlyEditor()
				.find('h1').should('contain', 'Welcome to your new collective')
			cy.get('.app-content-list-item.toplevel')
				.find('button.icon.add')
				.should('not.exist')
			cy.getEditor().should('not.exist')
		})
		it('Allows setting a collective share to editable', function() {
			cy.loginAs('bob')
			cy.visit('apps/collectives')
			cy.openCollectiveMenu('Share me')
			cy.clickMenuButton('Share link')
			cy.get('.sharing-entry .share-select')
				.click()
			cy.intercept('PUT', '**/_api/*/share/*').as('updateShare')
			cy.get('.sharing-entry .share-select .dropdown-item')
				.contains('Can edit')
				.click()
			cy.wait('@updateShare')
			cy.get('.toast-success').should('contain', 'Share link of collective "Share me" has been updated')
			cy.get('.sharing-entry .share-select')
				.should('contain', 'Can edit')
		})
		it('Allows opening and editing a shared (editable) collective', function() {
			cy.logout()
			cy.visit(shareUrl)
			// Do some handstands to ensure that new page with editor is loaded before we edit the title
			cy.intercept('POST', '**/_api/p/*/_pages/*').as('createPage')
			if (['stable26', 'stable27'].includes(Cypress.env('ncVersion'))) {
				cy.intercept('PUT', '**/apps/text/public/session/create').as('textCreateSession')
			} else {
				cy.intercept('PUT', '**/apps/text/public/session/*/create').as('textCreateSession')
			}
			cy.contains('.app-content-list-item', 'Share me')
				.find('button.action-button-add')
				.click()
			cy.wait(['@createPage', '@textCreateSession'])
			cy.getEditor()
				.should('be.visible')
			cy.get('#titleform input.title')
				.should('not.have.attr', 'disabled')
			cy.get('#titleform input.title')
				.should('have.value', '')
			cy.get('#titleform input.title')
				.type('New page')
			cy.getEditorContent(true)
				.type('New content')
			cy.get('button.titleform-button')
				.click()
		})
		it('Allows unsharing a collective', function() {
			cy.loginAs('bob')
			cy.visit('apps/collectives')
			cy.openCollectiveMenu('Share me')
			cy.clickMenuButton('Share link')
			cy.get('.sharing-entry__actions')
				.click()
			cy.intercept('DELETE', '**/_api/*/share/*').as('deleteShare')
			cy.get('.unshare-button')
				.click()
			cy.wait('@deleteShare')
			cy.get('.toast-success').should('contain', 'Collective "Share me" has been unshared')
		})
		it('Opening unshared collective fails', function() {
			cy.logout()
			cy.visit(shareUrl, { failOnStatusCode: false })
			cy.get('.body-login-container').contains(/(File|Page) not found/)
		})
	})
})
