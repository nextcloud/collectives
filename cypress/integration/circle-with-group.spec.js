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

/**
 * Regression test for #121.
 * When user is not a direct member of a circle,
 * but is a member of a group linked to the circle,
 * then access to the collective only works half-way:
 * You can see the collective on the list,
 * but you can't see a list of pages or access any page.
 *
 */
describe('Pages are accessible via group membership to circle', function() {
	before(function() {
		cy.login('jane', 'jane', '/apps/collectives')
		cy.seedCollective('Group Collective')
		cy.addGroupToCollective({
			group: 'Bobs Group',
			collective: 'Group Collective',
		})
		cy.logout()
		cy.clearCookies()
		cy.getCookies().should('be.empty')
	})

	it('Lists the collective', function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.get('.app-navigation').contains('Group Collective').click()
		cy.get('#text h1').should('contain', 'Welcome to your new collective')
		cy.logout()
	})
})
