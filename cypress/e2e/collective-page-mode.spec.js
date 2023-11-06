/**
 * @copyright Copyright (c) 2021 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
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
