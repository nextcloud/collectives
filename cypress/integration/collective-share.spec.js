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

/**
 *  Tests for basic Collectives functionality.
 */

describe('Collective Share', function() {
	let shareUrl

	before(function() {
		cy.login('bob')
		cy.seedCollective('Share me')
	})

	describe('collective share', function() {
		it('Allows sharing a collective', function() {
			cy.login('bob')
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
			cy.get('.collectives_list_item')
				.contains('li', 'Share me')
				.find('.action-item__menutoggle')
				.click()
			cy.intercept('POST', '**/_api/*/share').as('createShare')
			cy.get('button')
				.contains('Share link')
				.click()
			cy.wait('@createShare')
			cy.get('.popover__wrapper ul')
				.contains('Share link').should('not.be.visible')
			cy.get('.popover__wrapper ul')
				.contains('Copy share link').should('be.visible')
			cy.get('.popover__wrapper ul')
				.contains('Unshare').should('be.visible')
			cy.get('button')
				.contains('Copy share link')
				.click()
			cy.get('.toast-success').should('contain', 'Link copied to the clipboard.')
			cy.get('@clipBoardWriteText').should('have.been.calledOnce')
		})
		it('Allows opening a shared (non-editable) collective', function() {
			cy.visit(shareUrl)
			cy.get('#titleform input').should('have.value', 'Share me')
			cy.get('button.edit-button').should('not.exist')
			cy.get('#text h1').should('contain', 'Welcome to your new collective')
			cy.get('.app-content-list-item.toplevel')
				.get('button.icon.add').should('not.exist')
			cy.get('#editor-container').should('not.exist')
		})
		it('Allows toggling the editable flag for a collective share', function() {
			cy.login('bob')
			cy.get('.collectives_list_item')
				.contains('li', 'Share me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('input#shareEditable')
				.check({ force: true }).then(() => {
					cy.get('input#shareEditable')
						.should('be.checked')
				})
		})
		it('Allows opening and editing a shared (editable) collective', function() {
			cy.visit(shareUrl)
			cy.contains('.app-content-list-item', 'Share me')
				.find('button.icon-add')
				.click()
			cy.get('#titleform input.title')
				.should('have.value', '')
				.type('New page')
			cy.get('#editor > > .editor__content > div.ProseMirror', { timeout: Cypress.config('defaultCommandTimeout') * 2 })
				.type('New content')
			cy.get('button.edit-button')
				.click()
		})
		it('Allows unsharing a collective', function() {
			cy.login('bob')
			cy.get('.collectives_list_item')
				.contains('li', 'Share me')
				.find('.action-item__menutoggle')
				.click()
			cy.intercept('DELETE', '**/_api/*/share/*').as('deleteShare')
			cy.get('button')
				.contains('Unshare')
				.click()
			cy.wait('@deleteShare')
			cy.get('.popover__wrapper ul')
				.contains('Share link').should('be.visible')
			cy.get('.popover__wrapper ul')
				.contains('Copy share link').should('not.be.visible')
			cy.get('.popover__wrapper ul')
				.contains('Unshare').should('not.be.visible')
		})
		it('Opening unshared collective fails', function() {
			cy.visit(shareUrl, { failOnStatusCode: false })
			cy.get('.body-login-container').should('contain', 'File not found')
			cy.get('.infogroup').should('contain', 'The document could not be found on the server.')
		})
	})
})
