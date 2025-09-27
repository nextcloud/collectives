/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective page mode', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden')
			.seedPage('Day 1', '', 'Readme.md')
			.seedPage('Day 2', '', 'Readme.md')
	})

	beforeEach(function() {
		cy.loginAs('bob')
	})

	describe('Changing page mode', function() {
		it('Opens edit mode per default', function() {
			cy.seedCollectivePageMode('Our Garden', 1)
			cy.visit('/apps/collectives/Our Garden')
			// make sure the page list loaded properly
			cy.contains('.app-content-list-item a', 'Day 1')
			cy.openPage('Day 2')
			cy.getEditor()
				.should('be.visible')
			cy.getReadOnlyEditor()
				.should('not.be.visible')
		})

		it('Opens view mode per default', function() {
			cy.seedCollectivePageMode('Our Garden', 0)
			cy.visit('/apps/collectives/Our Garden')
			// make sure the page list loaded properly
			cy.contains('.app-content-list-item a', 'Day 1')
			cy.openPage('Day 2')
			cy.getReadOnlyEditor()
				.should('be.visible')
			cy.getEditor()
				.should('not.be.visible')
		})
	})
})
