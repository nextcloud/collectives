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
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Share me')
			.seedPage('Sharepage', '', 'Readme.md')
			.seedPage('Sharesubpage', '', 'Sharepage.md')
		cy.seedPageContent('Share%20me/Sharepage/Readme.md', '## Shared page')
	})

	describe('page share', function() {
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
			cy.get('button.action-item .icon-menu-sidebar').click()
			cy.get('#tab-button-sharing').click()
			cy.intercept('POST', '**/_api/*/_pages/*/share').as('createShare')
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
			cy.get('#titleform input').should('have.value', 'Sharepage')
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
			cy.get('button.action-item .icon-menu-sidebar').click()
			cy.get('#tab-button-sharing').click()
			cy.get('.sharing-entry .share-select')
				.click()
			cy.intercept('PUT', '**/_api/*/_pages/*/share/*').as('updateShare')
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
			cy.get('button.action-item .icon-menu-sidebar').click()
			cy.get('#tab-button-sharing').click()
			cy.get('.sharing-entry__actions')
				.click()
			cy.intercept('DELETE', '**/_api/*/_pages/*/share/*').as('deleteShare')
			cy.get('.unshare-button')
				.click()
			cy.wait('@deleteShare')
			cy.get('.toast-success').should('contain', 'Page "Sharepage" has been unshared')
		})
		it('Opening unshared page fails', function() {
			cy.logout()
			cy.visit(shareUrl, { failOnStatusCode: false })
			cy.get('.body-login-container').contains(/(File|Page) not found/)
		})
	})
})
