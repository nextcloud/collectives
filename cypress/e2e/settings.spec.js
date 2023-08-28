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
		cy.deleteAndSeedCollective('A Collective')
	})

	describe('Collectives folder setting', function() {
		it('Allows changing the collective user folder', function() {
			const randomFolder = Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)
			cy.login('bob')
			cy.get('#app-settings')
				.click()
			cy.intercept('PROPFIND', '**/remote.php/dav/files/**').as('propfindFolder')
			cy.get('input[name="userFolder"]')
				.click()
			cy.wait('@propfindFolder')
			cy.get('[data-dir=""] > a')
				.click()
			cy.get('[data-dir="/Collectives"]').should('not.exist')
			cy.get('#oc-dialog-filepicker-content > span > span > a.button-add')
				.click()
			cy.intercept('MKCOL', `**/remote.php/dav/files/**/${randomFolder}`).as('createFolder')
			cy.get('nav.newFolderMenu > ul > li > form > input[type="text"]')
				.type(`${randomFolder}{enter}`)
			cy.wait('@createFolder')
			cy.intercept('POST', '**/collectives/api/v1.0/settings/user').as('setCollectivesFolder')
			cy.get('.oc-dialog-buttonrow > button').contains('Choose')
				.click()
			cy.wait('@setCollectivesFolder')
			cy.log('Check if collectives are in configured user folder')
			cy.visit('/apps/files')
			cy.get('.files-fileList').should('contain', randomFolder)
			cy.get('.files-fileList a').contains(randomFolder).click()
			cy.get('.files-controls .breadcrumb').should('contain', randomFolder)
			cy.get('.files-fileList').should('contain', 'A Collective')
			cy.log('Change user folder back to default')
			cy.visit('/apps/collectives')
			cy.get('#app-settings')
				.click()
			cy.get('input[name="userFolder"]')
				.click()
			cy.get('[data-dir=""] > a, a[title="Home"]')
				.click()
			cy.get('tr[data-entryname="Collectives"]')
				.click()
			cy.get('.oc-dialog-buttonrow > button').contains('Choose')
				.click()
		})
	})
})
