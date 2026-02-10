/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Unified search', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('UnifiedSearchCollective')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/UnifiedSearchCollective')
	})

	it('Search for page title', function() {
		cy.get('.unified-search a, button.unified-search__button, .unified-search-menu button').click()
		cy.get('.unified-search__form input, .unified-search-modal input')
			.type('Day')
		cy.get('.unified-search__results-collectives-pages, .unified-search-modal__results')
			.should('contain', 'Day 1')
	})

	it('Search for page content', function() {
		cy.get('.unified-search a, button.unified-search__button, .unified-search-menu button').click()
		cy.get('.unified-search__form input, .unified-search-modal input')
			.type('yourself at home')
		cy.get('.unified-search__results-collectives-page-content, .unified-search-modal__results')
			.should('contain', 'Multiple people can edit')
	})
})
