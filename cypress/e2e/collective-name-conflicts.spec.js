/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective name conflicts', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteCollective('Preexisting Team')
		cy.deleteCollective('History Club')
		cy.deleteAndSeedCollective('Preexisting Collective')
		cy.circleFind('Preexisting Collective')
			.circleAddMember('jane')
		cy.seedCircle('Preexisting Team')
		cy.seedCircle('History Club', { visible: true, open: true })
		cy.loginAs('jane')
		cy.deleteCollective('Foreign Team')
		cy.seedCircle('Foreign Team', { visible: true, open: true })
	})

	it('Reports existing team', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.createCollective('Foreign Team')
		cy.get('.modal-collective-name-error').should('contain', 'A collective/team with this name already exists')
	})
	it('Reports existing collective', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.createCollective('Preexisting Collective')
		cy.get('.modal-collective-name-error').should('contain', 'A collective/team with this name already exists')
	})
	it('creates collectives by picking team', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.get('button').contains('New collective').click()
		cy.get('button span.teams-icon').click()
		// cy.get('.circle-selector ul').should('not.contain', 'Foreign')
		cy.get('.circle-selector li [title*=History]').click()
		cy.get('button').contains('Add members').click()
		cy.get('button').contains('Create').click()

		cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', 'History Club')
		cy.get('.toast-info').should('contain', 'Created collective "History Club" for existing team.')
	})
	it('collectives of visible teams only show for members', function() {
		cy.loginAs('jane')
		cy.visit('apps/collectives')
		cy.get('.app-navigation-entry').should('not.contain', 'History Club')
	})
	it('creates collectives for admins of corresponding team', function() {
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.createCollective('Preexisting Team')
		cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', 'Preexisting Team')
		cy.get('.toast-info').should(
			'contain',
			'Created collective "Preexisting Team" for existing team.',
		)
	})

	after(function() {
		cy.loginAs('bob')
		cy.deleteCollective('Preexisting Team')
		cy.deleteCollective('History Club')
	})
})
