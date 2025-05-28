/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page versions', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Versions Collective')
			.seedPage('Page', '', 'Readme.md')

		// A new version will not be created if the changes occur within less than one second of each other.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Page.md', 'v1')
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Page.md', 'v2')
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Page.md', 'v3')
			.wait(1100)
		cy.seedPageContent('Versions Collective/Page.md', 'v4')
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Versions Collective/Page')

		cy.get('button.app-sidebar__toggle').click()
		cy.get('#tab-button-versions').click()
	})

	describe('Sidebar versions tab', function() {
		it('Lists versions', function() {
			cy.getReadOnlyEditor()
				.should('contain', 'v4')

			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.should('have.length', 4)

			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.should('contain', 'Current version')

			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.should('contain', 'Initial version')
		})

		it('Open initial and current version', function() {
			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.contains('Initial version')
				.click()

			cy.get('.page-title-container')
				.find('.title-version')
				.should('be.visible')
			cy.getReadOnlyEditor()
				.should('contain', 'v1')

			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.contains('Current version')
				.click()

			cy.get('.page-title-container')
				.find('.title-version')
				.should('not.exist')
			cy.getReadOnlyEditor()
				.should('contain', 'v4')
		})

		it('Add label to version', function() {
			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.eq(1)
				.find('.list-item-content__actions')
				.click()

			cy.clickMenuButton('Name this version')

			cy.get('.version-label-modal input[type="text"]')
				.type('v3{enter}')

			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.should('contain', 'v3')
		})

		it('Compare initial and current version', function() {
			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.eq(3)
				.find('.list-item-content__actions')
				.click()

			cy.clickMenuButton('Compare to current version')

			cy.get('#viewer .text-editor')
				.should('contain', 'v1')
			cy.get('#viewer .text-editor')
				.should('contain', 'v4')

			cy.get('#viewer .header-close')
				.click()
		})

		it('Restore initial version', function() {
			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.eq(3)
				.find('.list-item-content__actions')
				.click()

			cy.intercept('MOVE', '**/dav/versions/**').as('moveVersion')
			cy.clickMenuButton('Restore version')
			cy.wait('@moveVersion')

			cy.reload()
			cy.getReadOnlyEditor()
				.should('contain', 'v1')
		})

		it('Delete version', function() {
			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.eq(2)
				.find('.list-item-content__actions')
				.click()

			cy.intercept('MOVE', '**/dav/versions/**').as('moveVersion')
			cy.clickMenuButton('Delete version')

			cy.get('.app-sidebar-tabs__content .version-list .list-item')
				.should('have.length', 3)
		})
	})
})
