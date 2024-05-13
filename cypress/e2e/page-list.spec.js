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

describe('Page list', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Our Garden')
			.as('garden')
			.seedPage('Target', '', 'Readme.md')
			.seedPage('Target Subpage', '', 'Target.md')
		// Wait 1 second to make sure that page order by time is right
		cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
		cy.then(() => this.garden)
			.seedPage('Day 1', '', 'Readme.md')
			.seedPage('Subpage Title', '', 'Day 1.md')
			.seedPage('DeepSubpage Title', 'Day 1', 'Subpage Title.md')
			.seedPage('Day 2', '', 'Readme.md')
			.seedPage('Page Title', '', 'Readme.md')
			.seedPage('Move me internal', '', 'Readme.md')
			.seedPage('Copy me internal', '', 'Readme.md')
			.seedPage('Move me external', '', 'Readme.md')
			.seedPage('Copy me external', '', 'Readme.md')
			.seedPage('#% special chars', '', 'Readme.md')
		cy.deleteAndSeedCollective('MoveCopyTargetCollective')
			.seedPage('Target external', '', 'Readme.md')
			.seedPage('Target Subpage external', '', 'Target external.md')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives/Our Garden')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Day 1')
	})

	describe('Sort order', function() {
		it('sorts pages by custom order by default', function() {
			cy.get('.app-content-list-item').eq(1)
				.should('contain', '#% special chars')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Target')
		})
		it('can sort pages by title/timestamp and sort order is persistent', function() {
			// Select sorting by title
			cy.get('span.sort-ascending-icon').click()
			cy.get('.sort-alphabetical-ascending-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Target')

			// Reload to test persistance of sort order
			cy.intercept('GET', '**/_api/*/_pages').as('getPages')
			cy.reload()
			cy.wait('@getPages')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Target')

			// Select sorting by timestamp
			cy.get('span.sort-alphabetical-ascending-icon').click()
			cy.get('button.action-button > span.sort-clock-ascending-outline-icon').click()
			cy.get('.app-content-list-item').last()
				.should('contain', 'Target')

			// Remove alternative sort order
			cy.get('span.sort-order-chip > button').click()
			cy.get('span.sort-ascending-icon')
				.should('be.visible')
			cy.get('.app-content-list-item').eq(1)
				.should('contain', '#% special chars')
			cy.get('.app-content-list-item').last()
				.should('contain', 'Target')
		})
	})

	describe('Move and copy a page using the modal', function() {
		it('Moves page to a subpage', function() {
			cy.openPageMenu('Move me internal')
			cy.clickMenuButton('Move or copy')
			cy.get('.picker-list li')
				.contains('Target')
				.click()
			cy.get('.picker-move-buttons button .arrow-down-icon')
				.click()
			cy.get('.picker-buttons button')
				.contains('Move page here')
				.click()

			cy.openPage('Target')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.first()
				.contains('Target Subpage')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.last()
				.contains('Move me internal')
		})

		it('Copies page to a subpage', function() {
			cy.openPageMenu('Copy me internal')
			cy.clickMenuButton('Move or copy')
			cy.get('.picker-list li')
				.contains('Target')
				.click()
			cy.get('.picker-move-buttons button .arrow-down-icon')
				.click()
			cy.get('.picker-buttons button')
				.contains('Copy page here')
				.click()

			cy.openPage('Target')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.first()
				.contains('Target Subpage')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.eq(1)
				.contains('Copy me internal')
			cy.contains('.page-list-drag-item', 'Target')
				.get('.page-list-indent .app-content-list-item')
				.last()
				.contains('Move me internal')
		})

		it('Moves page to a subpage in another collective', function() {
			cy.openPageMenu('Move me external')
			cy.clickMenuButton('Move or copy')
			cy.get('.crumbs-home')
				.click()
			cy.get('.picker-list li')
				.contains('MoveCopyTargetCollective')
				.click()
			cy.get('.picker-list li')
				.contains('Target external')
				.click()
			cy.get('.picker-move-buttons button .arrow-down-icon')
				.click()
			cy.get('.picker-buttons button')
				.contains('Move page to MoveCopyTargetCollective')
				.click()

			cy.visit('/apps/collectives/MoveCopyTargetCollective')
			cy.openPage('Target external')
			cy.contains('.page-list-drag-item', 'Target external')
				.get('.page-list-indent .app-content-list-item')
				.first()
				.contains('Target Subpage external')
			cy.contains('.page-list-drag-item', 'Target external')
				.get('.page-list-indent .app-content-list-item')
				.last()
				.contains('Move me external')
		})

		it('Copies page to a subpage in another collective', function() {
			cy.openPageMenu('Copy me external')
			cy.clickMenuButton('Move or copy')
			cy.get('.crumbs-home')
				.click()
			cy.get('.picker-list li')
				.contains('MoveCopyTargetCollective')
				.click()
			cy.get('.picker-list li')
				.contains('Target external')
				.click()
			cy.get('.picker-move-buttons button .arrow-down-icon')
				.click()
			cy.get('.picker-buttons button')
				.contains('Copy page to MoveCopyTargetCollective')
				.click()

			cy.visit('/apps/collectives/MoveCopyTargetCollective')
			cy.openPage('Target external')
			cy.contains('.page-list-drag-item', 'Target external')
				.get('.page-list-indent .app-content-list-item')
				.first()
				.contains('Target Subpage external')
			cy.contains('.page-list-drag-item', 'Target external')
				.get('.page-list-indent .app-content-list-item')
				.eq(1)
				.contains('Copy me external')
			cy.contains('.page-list-drag-item', 'Target external')
				.get('.page-list-indent .app-content-list-item')
				.last()
				.contains('Move me external')
		})
	})

	describe('Using the page list filter', function() {
		it('Shows only landing page and (sub)pages matching the filter string', function() {
			cy.get('input[name="pageFilter"]')
				.type('Title')
			cy.get('.app-content-list-item-line-one:visible').should('have.length', 4)
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
			cy.visit('apps/collectives/_/print/Our%20Garden', {
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
		it('allows to trash and restore page with subpage and attachment', function() {
			cy.openPage('Day 1')

			// Insert attachment
			cy.intercept({ method: 'POST', url: '**/text/attachment/upload*' }).as('attachmentUpload')
			cy.get('input[data-text-el="attachment-file-input"]')
				.selectFile('cypress/fixtures/test.png', { force: true })
			cy.wait('@attachmentUpload')

			cy.wait(1000) // eslint-disable-line cypress/no-unnecessary-waiting
			cy.getEditorContent(true)
				.type('text')
			cy.switchToViewMode()

			// Trash page
			cy.openPageMenu('Day 1')
			cy.clickMenuButton('Delete page and subpages')
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
			cy.get('.modal__page-trash button.modal-container__close').click()

			cy.openPage('Day 1')
			cy.getReadOnlyEditor()
				.find('img.image__main')
				.should('be.visible')
		})
	})
})
