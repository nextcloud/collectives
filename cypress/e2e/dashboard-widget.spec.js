/**
 * @copyright Copyright (c) 2023 Jonas <jonas@freesources.org>
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
 *  Tests for Collectives dashboard widget.
 */

describe('Collectives dashboard widget', function() {
	if (Cypress.env('ncVersion') !== 'stable25') {
		describe('Open dashboard widget', function() {
			before(function() {
				cy.loginAs('bob')
				cy.enableDashboardWidget('collectives-recent-pages')
				cy.visit('apps/collectives')
				cy.deleteAndSeedCollective('Dashboard Collective1')
				cy.seedPage('Page 1', '', 'Readme.md')
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
	}
})
