/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page content search', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden')
			.seedPage('Day 1', '', 'Readme.md')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Our Garden')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')

		cy.get('input[name="pageFilter"]').type('the')
		cy.get('.search-dialog-container', { timeout: 5000 })
			.should('be.visible')
			.as('searchDialog')
	})

	it('Shows search dialog', () => {
		cy.get('.search-dialog__info')
			.invoke('text')
			.invoke('trim')
			.should('equal', 'Found 9 matches for "the"')
	})

	it('Clears search', () => {
		cy.get('.search-dialog__buttons')
			.find('button[aria-label="Clear search"]')
			.click()
		cy.get('@searchDialog').should('not.exist')
	})

	it('Toggles highlight all', () => {
		cy.get('.search-dialog__highlight-all')
			.find('span.checkbox-radio-switch-checkbox')
			.click()

		cy.get('.search-dialog__info')
			.invoke('text')
			.invoke('trim')
			.should('equal', 'Match 1 of 9 for "the"')
	})

	it('Moves to next search', () => {
		cy.get('.search-dialog__buttons')
			.find('button[aria-label="Find next match"]')
			.click()

		cy.get('.search-dialog__info')
			.invoke('text')
			.invoke('trim')
			.should('equal', 'Match 2 of 9 for "the"')
	})

	it('Moves to previous search', () => {
		cy.get('.search-dialog__buttons')
			.find('button[aria-label="Find previous match"]')
			.click()

		cy.get('.search-dialog__info')
			.invoke('text')
			.invoke('trim')
			.should('equal', 'Match 9 of 9 for "the"')
	})
})
