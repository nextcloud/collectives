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

	it('Finds publish button', function() {
		cy.openCollectiveMenu('Our Garden')
		cy.get('button.action-button')
			.should('contain', 'Publish')
	})
})
