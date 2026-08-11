/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective publish', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden').as('garden')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Our Garden')
	})

	it('opens publish modal on publish button click', function() {
		// Open publish modal
		cy.openCollectiveMenu('Our Garden')
		cy.contains('button.action-button', 'Publish')
			.click({ force: true })
		cy.get('.collective-publish-modal')
			.should('contain', 'Our Garden')

		// Close modal and check it can open after clicking publish button again
		cy.get('.collective-publish-modal button[aria-label="Close"]')
			.click({ force: true })
		cy.get('.collective-publish-modal')
			.should('not.exist')
		cy.contains('button.action-button', 'Publish')
			.click({ force: true })
		cy.get('.collective-publish-modal')
			.should('contain', 'Our Garden')
	})
})
