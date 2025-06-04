/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective members', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Members Collective')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.openCollectiveMenu('Members Collective')
		cy.clickMenuButton('Manage members')
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
			cy.intercept('GET', '**/circles/circles/*/members?**').as('getCircleMembers')
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
			cy.intercept('GET', '**/circles/circles/*/members?**').as('getCircleMembers')
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
			cy.intercept('GET', '**/circles/circles/*/members?**').as('getCircleMembers')
			cy.get('button.action-button')
				.contains('Remove')
				.click()
			cy.wait('@removeCircleMember')
			cy.wait('@getCircleMembers')

			cy.get('.current-members .member-row').should('not.contain', member)
		})
	})
})
