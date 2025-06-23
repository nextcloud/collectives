/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page mentions', function() {
	if (!['stable30'].includes(Cypress.env('ncVersion'))) {
		before(function() {
			cy.loginAs('bob')
			cy.deleteAndSeedCollective('Mention Collective')
				.seedPage('Page 1', '', 'Readme.md')
			cy.circleFind('Mention Collective')
				.circleAddMember('alice')

			cy.visit('/apps/collectives/Mention Collective/Page 1')
			cy.getEditorContent(true)
				.type('Bob mentions @alice')
			cy.get('.tippy-content')
				.contains('alice')
				.click()
			cy.getEditor()
				.find('.save-status')
				.click()
		})

		describe('Mentioning another member in a page', function() {
			it('Notification is shown to mentioned member', function() {
				cy.loginAs('alice')
				cy.visit('/apps/collectives/Mention Collective')
				cy.get('.notifications-button')
					.click()

				cy.get('#header-menu-notifications')
					.find('.notification .rich-text--wrapper')
					.invoke('text')
					.should('match', /bob \s*has mentioned you in .*Mention Collective - .*Page 1/)
			})
		})
	}
})
