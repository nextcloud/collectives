/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Read-only collective', function() {
	before(function() {
		cy.loginAs('alice')
		cy.deleteAndSeedCollective('PermissionCollective')
			.seedPage('SecondPage')
		cy.circleFind('PermissionCollective')
			.circleAddMember('bob')
		cy.seedCollectivePermissions('PermissionCollective', 'edit', 4)
	})

	describe('in read-only collective', function() {
		beforeEach(function() {
			cy.loginAs('bob')
			cy.visit('/apps/collectives/PermissionCollective/SecondPage')
		})

		it('not able to edit collective', function() {
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.attr', 'disabled')
			cy.get('button.titleform-button').should('not.exist')
			cy.get('.app-content-list-item.toplevel')
				.find('button.icon.add')
				.should('not.exist')
			cy.getEditor()
				.should('not.exist')
		})

		it('actions menu with outline toggle is there', function() {
			cy.get('[data-cy-collectives="page-title-container"] button.action-item__menutoggle')
				.click()
			cy.get('button.action-button')
				.contains('Show outline')
		})
	})
})
