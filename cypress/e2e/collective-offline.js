/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective offline', function() {
	let collectiveId, pageId, pageSlugUrl
	const collectiveName = 'Offline'
	const pageName = 'First Page'
	const pageSlug = 'First-Page'

	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective(collectiveName)
			.seedPage(pageName, '', 'Readme.md').then(({ collectiveId: cid, pageId: pid }) => {
				collectiveId = cid
				pageId = pid
				pageSlugUrl = `/apps/collectives/${collectiveName}-${collectiveId}/${pageSlug}-${pageId}`
			})
	})

	beforeEach(function() {
		cy.goOnline()
		cy.loginAs('bob')
		cy.visit(pageSlugUrl)
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', pageName)
	})

	it('Shows offline indicator', function() {
		cy.getEditorContent()
		cy.get('.offline-indicator').should('not.exist')
		Cypress.on('uncaught:exception', (err) => {
			if (err.message.includes('Network Error')) {
				// do not fail the test
				return false
			}
		})
		cy.goOffline()
		cy.get('.offline-indicator').should('be.visible')
		cy.goOnline()
	})

	it('Shows offline state in version tab', function() {
		cy.getEditorContent()
		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-versions').click()

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('contain', 'Current version')

		cy.goOffline()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('not.exist')
		cy.get('.app-sidebar-tabs__content .versions-container')
			.should('contain', 'Offline')
		cy.goOnline()
	})
})
