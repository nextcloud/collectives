/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collectives dashboard widget', function() {
	describe('Open dashboard widget', function() {
		before(function() {
			cy.loginAs('bob')
			cy.enableDashboardWidget('collectives-recent-pages')
			cy.deleteAndSeedCollective('Dashboard Collective1')
				.seedPage('Page 1', '', 'Readme.md')
		})
		it('Lists pages in the dashboard widget', function() {
			cy.visit('/apps/dashboard/')
			cy.get('.panel--header')
				.contains('Recent pages')
			cy.get('.panel--content').as('panelContent')
			cy.get('@panelContent')
				.find('li').should('contain', 'Landing page')
			cy.get('@panelContent')
				.find('li').should('contain', 'Page 1')
		})
	})
})
