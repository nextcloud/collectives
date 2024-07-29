/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Regression test for #121.
 * When user is not a direct member of a team,
 * but is a member of a group linked to the team,
 * then access to the collective only works half-way:
 * You can see the collective on the list,
 * but you can't see a list of pages or access any page.
 *
 */
describe('Pages are accessible via group membership to team', function() {
	before(function() {
		cy.loginAs('jane')
		cy.deleteAndSeedCollective('Group Collective')
		cy.circleFind('Group Collective')
			.circleAddMember('Bobs Group', 2)
			.circleSetMemberLevel(8)
	})

	it('Lists the collective', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.get('.app-navigation').contains('Group Collective').click()
		cy.getReadOnlyEditor()
			.find('h1').should('contain', 'Welcome to your new collective')
	})
})
