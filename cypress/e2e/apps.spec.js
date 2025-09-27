/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('The apps', function() {
	describe('Collectives', function() {
		it('allows creating a new collective', function() {
			cy.loginAs('jane')
			cy.visit('apps/collectives')
			cy.get('#app-navigation-vue')
				.should('contain', 'New collective')
		})
	})

	/**
	 * Regression test for #110 and #117.
	 * Without the teams app the whole server became unresponsive.
	 * If we have this regression this test will not only fail
	 * it will also break all following tests.
	 *
	 */
	describe('Disabled teams app does not break files view', function() {
		before(function() {
			cy.loginAs('admin')
			cy.disableApp('circles')
		})

		after(function() {
			cy.loginAs('admin')
			cy.enableApp('circles')
		})

		it('Renders the default files list', function() {
			cy.loginAs('jane')
			cy.visit('apps/files')
			cy.fileList().should('contain', 'welcome')
		})
	})
})
