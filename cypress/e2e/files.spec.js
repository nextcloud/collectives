/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Files app', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Preexisting Collective')
	})

	it('has a matching folder', function() {
		const breadcrumbsSelector = '[data-cy-files-content-breadcrumbs] :is(a, button)'
		cy.visit('/apps/files')

		cy.openFile('Collectives')
		cy.get(breadcrumbsSelector).should('contain', 'Collectives')
		cy.openFile('Preexisting Collective')
		cy.get(breadcrumbsSelector).should('contain', 'Preexisting Collective')
		cy.fileList().should('contain', 'Readme')
		cy.fileList().should('contain', '.md')
		cy.get('.filelist-collectives-wrapper')
			.should('contain', 'The content of this folder is best viewed in the Collectives app.')
	})
})
