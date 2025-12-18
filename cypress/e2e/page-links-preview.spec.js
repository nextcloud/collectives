/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const baseUrl = Cypress.env('baseUrl')

describe('Page link preview handling', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Link Preview Testing')
			.seedPage('Link Source', '', 'Readme.md')
			.seedPage('Link Target', '', 'Readme.md')
			.then(({ pageId }) => {
				const pageUrls = [
					`${baseUrl}/index.php/apps/collectives/Link%20Preview%20Testing/Link%20Target?fileId=${pageId}`,
					`${baseUrl}/index.php/apps/collectives/Link%20Preview%20Testing/Link%20Target`,
					`${baseUrl}/index.php/apps/collectives/p/qqqYoCgYRnZ598p/Link%20Preview%20Testing/Link%20Target?fileId=${pageId}`,
					`${baseUrl}/index.php/apps/collectives/p/qqqYoCgYRnZ598p/Link%20Preview%20Testing/Link%20Target`,
				]
				cy.seedPageContent('Link%20Preview%20Testing/Link%20Target.md', 'Some content')
					.seedPageContent('Link%20Preview%20Testing/Link%20Source.md', `
## Link previews to own Collective

[Internal link to page with fileId](${pageUrls[0]} (preview))

[Internal link to page without fileId](${pageUrls[1]} (preview))

[Public link to page with fileId](${pageUrls[2]} (preview))

[Public link to page without fileId](${pageUrls[3]} (preview))
						`)
			})
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Link Preview Testing/Link Source')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Link Target')
	})

	it('Shows previews in view and edit mode', function() {
		cy.getEditorContent()
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)

		cy.switchToEditMode()
		cy.getEditorContent(true)
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)
	})

	let shareUrl

	it('Share the collective', function() {
		cy.visit({
			url: '/apps/collectives',
			onBeforeLoad(win) {
				// navigator.clipboard doesn't exist on HTTP requests (in CI), so let's create it
				if (!win.navigator.clipboard) {
					win.navigator.clipboard = {
						__proto__: {
							writeText: () => {
							},
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
		cy.openCollectiveMenu('Link Preview Testing')
		cy.clickMenuButton('Share link')
		cy.intercept('POST', '**/api/v1.0/collectives/*/shares').as('createShare')
		cy.get('.sharing-entry button.new-share-link')
			.click()
		cy.wait('@createShare')
		cy.get('.sharing-entry .share-select')
			.click()
		cy.intercept('PUT', '**/api/v1.0/collectives/*/shares/*').as('updateShare')
		cy.get('.sharing-entry .share-select .dropdown-item')
			.contains('Can edit')
			.click()
		cy.wait('@updateShare')
		cy.get('button.sharing-entry__copy')
			.click()
		cy.get('@clipBoardWriteText').should('have.been.calledOnce')
	})

	it('Public share: Shows previews in view and edit mode', function() {
		cy.logout()
		cy.visit(`${shareUrl}/Link Source`)

		cy.getEditorContent()
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)

		cy.switchToEditMode()
		cy.getEditorContent(true)
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)
	})
})
