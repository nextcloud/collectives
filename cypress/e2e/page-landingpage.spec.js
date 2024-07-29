/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const collective = 'Landingpage Collective'

describe('Page landing page', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective(collective)
			.seedPage('Page 1', '', 'Readme.md')
			.seedPage('Page 2', '', 'Readme.md')
			.then(collective => {
				// Wait 1 to make sure that page order by time is right
				cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
				cy.wrap(collective)
					.seedPage('Page 3', '', 'Readme.md')
			})
		cy.circleFind(collective).circleAddMember('alice')
		cy.circleFind(collective).circleAddMember('jane')
		cy.circleFind(collective).circleAddMember('john')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit(`/apps/collectives/${collective}`)
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Page 1')
	})

	describe('Displays recent pages', function() {
		it('Allows to toggle recent pages widget', function() {
			cy.get('.recent-pages-widget .recent-page-tile')
				.contains('Page 3')

			cy.get('.recent-pages-widget .recent-pages-title')
				.click()
			cy.get('.recent-pages-widget .recent-page-tile')
				.should('not.be.visible')

			cy.reload()

			cy.get('.recent-pages-widget .recent-page-tile')
				.should('not.be.visible')
			cy.get('.recent-pages-widget .recent-pages-title')
				.click()
			cy.get('.recent-pages-widget .recent-page-tile')
				.contains('Page 3')
		})

		it('Allows to open page from recent pages widget', function() {
			cy.get('.recent-pages-widget .recent-page-tile')
				.contains('Page 3')
				.click()
			cy.url().should('include', `/apps/collectives/${encodeURIComponent(collective)}/${encodeURIComponent('Page 3')}`)
		})
	})

	describe('Displays recent members', function() {
		it('Allows to open members modal as admin', function() {
			cy.get('.members-widget img[src*="/alice/"]')
			cy.get('.members-widget .button-vue[title="Show members"]')
				.click()

			cy.get('.current-members').contains('.member-row', 'alice')
				.find('.member-row__actions')
				.should('exist')
		})

		it('Allows to open members modal as member', function() {
			cy.loginAs('alice')
			cy.visit(`/apps/collectives/${collective}`)
			cy.get('.members-widget img[src*="/bob/"]')
			cy.get('.members-widget .button-vue[title="Show members"]')
				.click()

			cy.get('.current-members').contains('.member-row', 'bob')
				.find('.member-row__actions')
				.should('not.exist')
		})
	})
})
