/**
 * @copyright Copyright (c) 2022 Jonas <jonas@freesources.org>
 *
 * @author Jonas <jonas@freesources.org>
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
 *  Tests for app-wide settings functionality.
 */

describe('Settings', function() {
	before(function() {
		cy.login('bob')
		cy.seedCollective('A Collective')
	})

	describe('Collectives folder setting', function() {
		it('Allows changing the collective user folder', function() {
			cy.login('bob')
			cy.get('#app-settings')
				.click()
			cy.get('input#userFolder')
				.click()
			cy.get('div[data-dir=""] > a')
				.click()
			cy.get('div[data-dir="/Collectives"]').should('not.exist')
			cy.get('#oc-dialog-filepicker-content > span > span > a.button-add')
				.click()
			cy.get('nav.newFolderMenu > ul > li > form > input[type="text"]')
				.type('OtherFolder{enter}')
			cy.get('nav.newFolderMenu > ul > li > form').submit()
			cy.get('.oc-dialog-buttonrow > button')
				.click()
			cy.log('Check if collectives are in configured user folder')
			cy.visit('/apps/files')
			cy.get('#fileList').should('contain', 'OtherFolder')
			cy.get('#fileList a').contains('OtherFolder').click()
			cy.get('#controls .breadcrumb').should('contain', 'OtherFolder')
			cy.get('#fileList').should('contain', 'A Collective')
		})
	})
})
