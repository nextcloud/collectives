/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Settings', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('A Collective')
	})

	describe('Collectives folder setting', function() {
		it('Allows changing the collective user folder', function() {
			const randomFolder = Math.random().toString(36).replace(/[^a-z]+/g, '').slice(0, 10)
			const breadcrumbsSelector = '.files-controls .breadcrumb, [data-cy-files-content-breadcrumbs] a'
			cy.loginAs('bob')
			cy.visit('apps/collectives/A%20Collective')
			cy.get('button.app-navigation-toggle').click()

			cy.get('#app-settings')
				.click()
			cy.intercept('PROPFIND', '**/remote.php/dav/files/**').as('propfindFolder')
			cy.get('input[name="userFolder"]')
				.click()
			cy.wait('@propfindFolder')

			// Open home folder
			cy.get('.file-picker nav [title="Home"]')
				.click()
			cy.wait('@propfindFolder')

			// Create new folder
			cy.get('#oc-dialog-filepicker-content > span > span > a.button-add, .breadcrumb__actions button')
				.click()
			cy.intercept('MKCOL', `**/remote.php/dav/files/**/${randomFolder}`).as('createFolder')
			cy.get('nav.newFolderMenu > ul > li > form > input[type="text"], input[placeholder="New folder name"], input[placeholder="New folder"]')
				.type(`${randomFolder}{enter}`)
			cy.wait('@createFolder')
			// Wait until new view of empty folder is loaded
			cy.get('.file-picker .empty-content')

			// Select new created folder
			cy.intercept('POST', '**/collectives/api/v1.0/settings/user').as('setCollectivesFolder')
			cy.get('button').contains('Choose')
				.click()
			cy.wait('@setCollectivesFolder')
			cy.getCollectivesFolder()
				.should('be.equal', `/${randomFolder}`)

			// Check if collectives are found in new folder in Files app
			cy.log('Check if collectives are in configured user folder')
			cy.visit('/apps/files')
			cy.openFile(randomFolder)
			cy.get(breadcrumbsSelector).should('contain', randomFolder)
			cy.fileList().should('contain', 'A Collective')

			// Change user folder back to default
			cy.setCollectivesFolder('/Collectives')
		})
	})
})
