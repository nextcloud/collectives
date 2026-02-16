/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Preexisting Collective')
		cy.circleFind('Preexisting Collective')
			.circleAddMember('jane')
		cy.loginAs('jane')
	})

	it('has all the ui elements', function() {
		cy.wrap('Created just now ' + Math.random().toString(36).substr(2, 4))
			.as('randomName')
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.createCollective(this.randomName, ['jane', 'john'])
		cy.log('Check name in the disabled titleform')
		cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', this.randomName)
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.attr', 'disabled')
		cy.log('Check initial Readme.md')
		cy.getReadOnlyEditor()
			.find('h1').should('contain', 'Welcome')
		cy.getReadOnlyEditor()
			.find('h1').should('contain', this.randomName)
		cy.log('Allows creation of pages')
		cy.get('.app-content-list-item')
			.trigger('mouseover')
		cy.get('.app-content-list button.action-button-add')
			.should('have.attr', 'aria-label')
			.and('contain', 'Add a page')
		cy.deleteCollective(this.randomName)
	})
	it('can leave collective and undo', function() {
		cy.loginAs('jane')
		cy.visit('/apps/collectives/Preexisting%20Collective')
		cy.get('button.app-navigation-toggle').click()

		// Leave collective
		cy.openCollectiveMenu('Preexisting Collective')
		cy.clickMenuButton('Leave collective')
		cy.get('.app-navigation-entry')
			.contains('Preexisting Collective')
			.should('not.be.visible')

		// Undo leave collective
		cy.get('.toast-undo')
			.should('contain', 'You left collective Preexisting Collective')
		cy.get('.toast-undo button')
			.contains('Undo')
			.click()

		cy.get('.app-navigation-entry')
			.contains('Preexisting Collective')
			.should('be.visible')

		// Leave collective and wait for 10 seconds
		cy.openCollectiveMenu('Preexisting Collective')
		cy.intercept('PUT', '**/apps/circles/circles/**/leave').as('leaveCircle')
		cy.clickMenuButton('Leave collective')
		cy.get('.app-navigation-entry')
			.contains('Preexisting Collective')
			.should('not.be.visible')
		// Wait 10 extra seconds for the request (undo period)
		cy.wait('@leaveCircle', { requestTimeout: Cypress.config('requestTimeout') + 10010 })
		cy.get('.app-navigation__list')
			.contains('Preexisting Collective')
			.should('not.exist')
	})
	it('cannot leave collective as last member', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')

		cy.openCollectiveMenu('Preexisting Collective')
		// No leave collective option
		cy.get('button.action-button')
			.contains('Leave collective')
			.should('not.exist')
	})

	it('reloading works', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Preexisting%20Collective')
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Preexisting Collective')
		cy.reload()
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Preexisting Collective')
	})
})
