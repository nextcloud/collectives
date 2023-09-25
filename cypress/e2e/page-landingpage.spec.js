/**
 * @copyright Copyright (c) 2023 Jonas <jonas@nextcloud.com>
 *
 * @author Jonas <jonas@nextcloud.com>
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
 *  Tests for page details.
 */

const collective = 'Landingpage Collective'

describe('Page landing page', function() {
	before(function() {
		cy.login('bob', { route: '/apps/collectives' })
		cy.deleteAndSeedCollective(collective)
		cy.seedCircleMember(collective, 'alice')
		cy.seedCircleMember(collective, 'jane')
		cy.seedCircleMember(collective, 'john')
		cy.seedPage('Page 1', '', 'Readme.md')
		cy.seedPage('Page 2', '', 'Readme.md')
		cy.seedPage('Page 3', '', 'Readme.md')
	})

	beforeEach(function() {
		cy.login('bob', { route: `/apps/collectives/${collective}` })
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Page 1')
	})

	describe('Displays recent pages', function() {
		it('Allows to toggle recent pages widget', function() {
			cy.get('.recent-pages-widget .recent-page-tile')
				.contains('Page 2')

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
				.contains('Page 2')
				.click()
		})

		it('Allows to open page from recent pages widget', function() {
			cy.get('.recent-pages-widget .recent-page-tile')
				.contains('Page 2')
				.click()
			cy.url().should('include', `/apps/collectives/${encodeURIComponent(collective)}/${encodeURIComponent('Page 2')}`)
		})
	})

	describe('Displays recent members', function() {
		it('Allows to open members modal as admin', function() {
			cy.get('.members-widget .avatardiv[title="alice"]')
			cy.get('.members-widget .button-vue[title="Show members"]')
				.click()

			cy.get('.current-members').contains('.member-row', 'alice')
				.find('.member-row__actions')
				.should('exist')
		})

		it('Allows to open members modal as member', function() {
			cy.login('alice', { route: `/apps/collectives/${collective}` })
			cy.get('.members-widget .avatardiv[title="bob"]')
			cy.get('.members-widget .button-vue[title="Show members"]')
				.click()

			cy.get('.current-members').contains('.member-row', 'bob')
				.find('.member-row__actions')
				.should('not.exist')
		})
	})
})
