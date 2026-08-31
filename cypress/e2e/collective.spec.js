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
		const randomName = 'Created just now ' + Math.random().toString(36).substr(2, 4)
		cy.loginAs('bob')
		cy.visit('apps/collectives')
		cy.createCollective(randomName, ['jane', 'john'])
		cy.log('Check name in the disabled titleform')
		cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', randomName)
		cy.get('[data-cy-collectives="page-title-container"] input').should('have.attr', 'disabled')
		cy.log('Check initial Readme.md')
		cy.getReadOnlyEditor()
			.find('h1').should('contain', 'Welcome')
		cy.getReadOnlyEditor()
			.find('h1').should('contain', randomName)
		cy.log('Allows creation of pages')
		cy.get('.page-list-headerbar button[aria-label="Add a page"]')
			.should('be.visible')
		cy.visit('apps/collectives/Preexisting Collective')
		cy.deleteCollective(randomName)
	})
	it('can leave collective and undo', function() {
		cy.loginAs('jane')
		cy.visit('/apps/collectives/Preexisting%20Collective')

		// Leave collective
		cy.openCollectiveMenu('Preexisting Collective')
		cy.clickMenuButton('Leave collective')
		cy.openCollectiveSelector()
		cy.get('.app-navigation-entry')
			.contains('Preexisting Collective')
			.should('not.be.visible')

		// Undo leave collective
		cy.get('[role="alert"]')
			.should('contain', 'You left collective Preexisting Collective')
		cy.get('[role="alert"] button')
			.contains('Undo')
			.click()

		cy.openCollectiveSelector()
		cy.get('.app-navigation-entry')
			.contains('Preexisting Collective')
			.should('be.visible')

		// Leave collective and wait for 10 seconds
		cy.openCollectiveMenu('Preexisting Collective')
		cy.intercept('PUT', '**/apps/circles/circles/**/leave').as('leaveCircle')
		cy.clickMenuButton('Leave collective')
		cy.openCollectiveSelector()
		cy.get('.app-navigation-entry')
			.contains('Preexisting Collective')
			.should('not.be.visible')
		// Wait 10 extra seconds for the request (undo period)
		cy.wait('@leaveCircle', { requestTimeout: Cypress.config('requestTimeout') + 10010 })
		cy.openCollectiveSelector()
		cy.get('.app-navigation__list')
			.contains('Preexisting Collective')
			.should('not.exist')
	})
	it('cannot leave collective as last member', function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Preexisting Collective')

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
