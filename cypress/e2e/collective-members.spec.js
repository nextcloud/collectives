/**
 * @copyright Copyright (c) 2022 Jonas <jonas@freesources.org>
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
 *  Tests for Collectives settings.
 */

describe('Collective members', function() {
	before(function() {
		cy.login('bob')
		cy.deleteCollective('Members Collective')
		cy.deleteAndSeedCollective('Members Collective')
	})

	beforeEach(function() {
		cy.login('bob')

		cy.get('.collectives_list_item')
			.contains('li', 'Members Collective')
			.find('.action-item__menutoggle')
			.click({ force: true })
		cy.get('button.action-button')
			.contains('Manage members')
			.click()
	})

	describe('Manage members', function() {
		it('Allows to add members', function() {
			const addMembers = ['alice', 'Bobs Group']
			for (const member of addMembers) {
				cy.get('.member-picker input[type="text"]').clear()
				cy.get('.member-picker input[type="text"]').type(member)
				cy.get('.member-search-results .member-row').contains(member).click()
				cy.get('.current-members .member-row').should('contain', member)
				cy.get('.member-search-results .member-row').should('not.exist')
			}
		})

		it('Allows to change membership management', function() {
			const member = 'Bobs Group'

			// Promote to admin
			cy.get('.current-members').contains('.member-row', member)
				.find('.member-row__actions')
				.click()
			cy.intercept('PUT', '**/circles/circles/*/members/*/level').as('updateCircleMemberLevel')
			cy.intercept('GET', '**/circles/circles/*/members').as('getCircleMembers')
			cy.get('button.action-button')
				.contains('Promote to admin')
				.click()
			cy.wait('@updateCircleMemberLevel')
			cy.wait('@getCircleMembers')
			cy.get('.current-members').contains('.member-row', member)
				.should('contain', '(admin)')

			// Demote to moderator
			cy.get('.current-members').contains('.member-row', member)
				.find('.member-row__actions')
				.click()
			cy.intercept('PUT', '**/circles/circles/*/members/*/level').as('updateCircleMemberLevel')
			cy.intercept('GET', '**/circles/circles/*/members').as('getCircleMembers')
			cy.get('button.action-button')
				.contains('Demote to moderator')
				.click()
			cy.wait('@updateCircleMemberLevel')
			cy.wait('@getCircleMembers')
			cy.get('.current-members').contains('.member-row', member)
				.should('contain', '(moderator)')
		})

		it('Allows to remove member', function() {
			const member = 'alice'

			cy.get('.current-members').contains('.member-row', member)
				.find('.member-row__actions')
				.click()
			cy.intercept('DELETE', '**/circles/circles/*/members/*').as('removeCircleMember')
			cy.intercept('GET', '**/circles/circles/*/members').as('getCircleMembers')
			cy.get('button.action-button')
				.contains('Remove')
				.click()
			cy.wait('@removeCircleMember')
			cy.wait('@getCircleMembers')

			cy.get('.current-members .member-row').should('not.contain', member)
		})
	})
})
