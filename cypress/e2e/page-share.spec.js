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
			cy.get('button.app-sidebar__toggle').click()
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
			cy.get('button.app-sidebar__toggle').click()
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
			cy.get('button.app-sidebar__toggle').click()
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

	describe('page share with password protection', function() {
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
		it('Allows adding password protection to a page share', function() {
			cy.loginAs('bob')
			cy.visit('/apps/collectives')
			cy.openCollective('Share me')
			cy.openPage('Sharepage')
			cy.get('button.app-sidebar__toggle').click()
			cy.get('#tab-button-sharing').click()
			cy.get('.sharing-entry__actions').click()
			cy.get('button').contains('Advanced settings').click()
			cy.get('.sharing-entry__settings').contains('Set password').click()
			cy.get('.sharing-entry__settings input[type="password"]').type('{selectAll}password')

			cy.intercept('PUT', '**/_api/*/_pages/*/share/*').as('updateShare')
			cy.get('.sharing-entry__settings button').contains('Update share').click()
			cy.wait('@updateShare')
			cy.get('.toast-success').should('contain', 'Share link of page "Sharepage" has been updated')
		})
		it('Allows opening a password-protected shared (non-editable) page', function() {
			cy.logout()
			cy.visit(shareUrl)
			cy.get('#password-input-form input[type="password"]').type('password')
			cy.get('#password-input-form input[type="submit"]').click()

			cy.get('#titleform input').should('have.value', 'Sharepage')
			cy.getReadOnlyEditor()
				.should('be.visible')
				.find('h2').should('contain', 'Shared page')
			cy.get('.app-content-list-item.toplevel')
				.should('contain', 'Sharepage')
		})
	})

	describe('page share with enforced password protection', function() {
		before(function() {
			cy.loginAs('admin')
			cy.setAppConfig('core', 'shareapi_enable_link_password_by_default', 'yes')
			cy.setAppConfig('core', 'shareapi_enforce_links_password', 'yes')
			cy.logout()
		})
		it('Allows sharing a page with enforced password protection', function() {
			cy.loginAs('bob')
			cy.visit('/apps/collectives')
			cy.openCollective('Share me')
			cy.openPage('Sharesecondpage')
			cy.get('button.app-sidebar__toggle').click()
			cy.get('#tab-button-sharing').click()
			cy.get('.sharing-entry button.new-share-link')
				.click()
			cy.get('input[autocomplete="new-password"][placeholder="Password"]').type('{selectAll}password')
			cy.intercept('POST', '**/_api/*/_pages/*/share').as('createShare')
			cy.get('button').contains('Create share').click()
			cy.wait('@createShare')
			cy.get('.toast-success').should('contain', 'Page "Sharesecondpage" has been shared')

			cy.get('.sharing-entry__actions').click()
			cy.get('button').contains('Advanced settings').click()
			cy.get('.sharing-entry__settings input[type="checkbox"]')
				.should('be.checked')
				.and('have.attr', 'disabled')
			cy.get('.sharing-entry__settings input[type="password"]').type('{selectAll}password')
		})
		after(function() {
			cy.loginAs('admin')
			cy.setAppConfig('core', 'shareapi_enable_link_password_by_default', 'no')
			cy.setAppConfig('core', 'shareapi_enforce_links_password', 'no')
			cy.logout()
		})
	})
})
