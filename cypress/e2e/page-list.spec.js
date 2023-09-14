/**
 * @copyright Copyright (c) 2023 Max <max@nextcloud.com>
 *
 * @author Max <max@nextcloud.com>
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
 *  Tests for the page list.
 */

describe('Page list', function() {
	before(function() {
		cy.login('bob', { route: '/apps/collectives' })
		cy.deleteAndSeedCollective('Our Garden')
		cy.seedPage('Day 1', '', 'Readme.md')
		// Wait 1 second to make sure that page order by time is right
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.seedPage('Day 2', '', 'Readme.md')
		cy.seedPage('Page Title', '', 'Readme.md')
		cy.seedPage('Subpage Title', '', 'Day 1.md')
		cy.seedPage('#% special chars', '', 'Readme.md')
	})

	beforeEach(function() {
		cy.login('bob', { route: '/apps/collectives/Our Garden' })
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	describe('Sort order', function() {
		it('sorts pages by custom order by default', function() {
			cy.get('.app-content-list-item').eq(1)
				.should('contain', '#% special chars')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')
		})
		it('can sort pages by title/timestamp and sort order is persistent', function() {
			// Select sorting by title
			cy.get('span.sort-ascending-icon').click()
			cy.get('.sort-alphabetical-ascending-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Page Title')

			// Reload to test persistance of sort order
			cy.intercept('GET', '**/_api/*/_pages').as('getPages')
			cy.reload()
			cy.wait('@getPages')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Page Title')

			// Select sorting by timestamp
			cy.get('span.sort-alphabetical-ascending-icon').click()
			cy.get('button.action-button > span.sort-clock-ascending-outline-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')

			// Remove alternative sort order
			cy.get('span.sort-order-chip > button').click()
			cy.get('span.sort-ascending-icon')
				.should('be.visible')
			cy.get('.app-content-list-item').eq(1)
				.should('contain', '#% special chars')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Day 1')
		})
	})

	describe('Move a page using the modal', function() {
		it('Moves page to a subpage', function() {
			cy.seedPage('Move me', '', 'Readme.md')
			cy.seedPage('Target', '', 'Readme.md')
			cy.seedPage('Target Subpage', '', 'Target.md')
			cy.visit('/apps/collectives/Our%20Garden')
			cy.contains('.app-content-list-item', 'Move me')
				.find('.action-item__menutoggle')
				.click({ force: true })
			cy.get('button.action-button')
				.contains('Move page')
				.click()
			cy.get('.picker-page-list li')
				.contains('Target')
				.click()
			cy.get('.picker-move-buttons button .arrow-down-icon')
				.click()
			cy.get('.picker-buttons button')
				.contains('Move page here')
				.click()

			cy.visit('/apps/collectives/Our%20Garden/Target')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.first()
				.contains('Target Subpage')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.last()
				.contains('Move me')
		})
	})

	describe('Using the page list filter', function() {
		it('Shows only landing page and (sub)pages matching the filter string', function() {
			cy.get('input[name="pageFilter"]')
				.type('Title')
			cy.get('.app-content-list-item-line-one:visible').should('have.length', 3)
			cy.get('.app-content-list-item-line-one:visible').eq(0)
				.should('contain', 'Our Garden')
			cy.get('.app-content-list-item-line-one:visible').eq(1)
				.should('contain', 'Page Title')
			cy.get('.app-content-list-item-line-one:visible').eq(2)
				.should('contain', 'Subpage Title')
		})
	})

	describe('Print view', function() {
		it('renders all the pages', function() {
			let printStub
			cy.visit('/apps/collectives/_/print/Our%20Garden', {
				onBeforeLoad: (win) => {
					printStub = cy.stub(win, 'print').as('print')
				},
			})
			cy.get('main')
				.should('contain', 'Preparing collective for exporting or printing')
			cy.get('#page-title-collective').should('contain', 'Our Garden')
			cy.get('main').should('contain', 'Day 2')
			cy.get('@print').should('be.called')
				.then(() => {
					printStub.restore()
				})
		})
	})

	describe('Page trash', function() {

		// Insert attachment once
		before(function() {
			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			cy.intercept({ method: 'POST', url: '**/text/attachment/upload*' }).as('attachmentUpload')
			cy.get('input[data-text-el="attachment-file-input"]')
				.selectFile('cypress/fixtures/test.png', { force: true })
			cy.wait('@attachmentUpload')
			cy.switchPageMode(0)
		})

		it('allows to trash and restore page with subpage and attachment', function() {

			// Trash page
			cy.contains('.page-list .app-content-list-item', 'Day 1')
				.find('.action-item__menutoggle')
				.click({ force: true })
			cy.get('button.action-button')
				.contains('Delete page and subpages')
				.click()
			cy.get('.page-list .app-content-list-item')
				.should('not.contain', 'Day 1')

			// Restore page
			cy.get('.page-trash')
				.click()
			cy.contains('table tr', 'Day 1')
				.find('button')
				.contains('Restore')
				.click()
			cy.get('table tr')
				.should('not.exist')

			cy.visit('/apps/collectives/Our%20Garden/Day%201')
			if (Cypress.env('ncVersion') === 'stable25') {
				cy.getEditor()
					.find('img.image__main')
					.should('be.visible')
			} else {
				cy.getReadOnlyEditor()
					.find('img.image__main')
					.should('be.visible')
			}
		})
	})
})
