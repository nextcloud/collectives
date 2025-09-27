/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Collective', function() {
	const specialCollective = 'stupid !@#$%^&()_ special chars'

	before(function() {
		cy.loginAs('bob')
		cy.deleteCollective('Preexisting Team')
		cy.deleteCollective('History Club')
		cy.deleteCollective(specialCollective)
		cy.deleteAndSeedCollective('Preexisting Collective')
		cy.circleFind('Preexisting Collective')
			.circleAddMember('jane')
		cy.seedCircle('Preexisting Team')
		cy.seedCircle('History Club', { visible: true, open: true })
		cy.loginAs('jane')
		cy.deleteCollective('Foreign Team')
		cy.seedCircle('Foreign Team', { visible: true, open: true })
	})

	describe('in the files app', function() {
		before(function() {
			cy.loginAs('bob')
			cy.visit('/apps/files')
		})
		it('has a matching folder', function() {
			const breadcrumbsSelector = '.files-controls .breadcrumb, [data-cy-files-content-breadcrumbs] a'
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

	describe('name conflicts', function() {
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
		it(
			'collectives of visible teams only show for members',
			function() {
				cy.loginAs('jane')
				cy.visit('apps/collectives')
				cy.get('.app-navigation-entry').should('not.contain', 'History Club')
			},
		)
		it(
			'creates collectives for admins of corresponding team',
			function() {
				cy.loginAs('bob')
				cy.visit('apps/collectives')
				cy.createCollective('Preexisting Team')
				cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', 'Preexisting Team')
				cy.get('.toast-info').should(
					'contain',
					'Created collective "Preexisting Team" for existing team.',
				)
			},
		)
		after(function() {
			cy.deleteCollective('Preexisting Team')
			cy.deleteCollective('History Club')
		})
	})

	describe('non ascii characters', function() {
		it(
			'can handle special chars in collective name',
			function() {
				cy.loginAs('bob')
				cy.visit('apps/collectives')
				cy.createCollective(specialCollective)
				cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', specialCollective)
			},
		)

		after(function() {
			cy.deleteCollective(specialCollective)
		})
	})
	// Note: the different assertions in here
	// all happen without any page reload or navigation.
	//
	// Cookies are cleared after every test.
	// So in all but the first run it block
	// bob will be logged out.
	describe('after creation', function() {
		const randomName = 'Created just now ' + Math.random().toString(36).substr(2, 4)
		it('has all the ui elements', function() {
			cy.loginAs('bob')
			cy.visit('apps/collectives')
			cy.createCollective(randomName, ['jane', 'john'])
			cy.log('Check name in the disabled titleform')
			cy.get('[data-cy-collectives="page-title-container"] input').invoke('val').should('contain', randomName)
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.attr', 'disabled')
			cy.log('Check initial Readme.md')
			cy.getReadOnlyEditor()
				.find('h1').should('contain', 'Welcome to your new collective')
			cy.log('Allows creation of pages')
			cy.get('.app-content-list-item')
				.trigger('mouseover')
			cy.get('.app-content-list button.action-button-add')
				.should('have.attr', 'aria-label')
				.and('contain', 'Add a page')
			cy.deleteCollective(randomName)
		})
	})

	describe('leaving a collective', function() {
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
				.should('contain', 'Undo')
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
	})

	describe('reloading works', function() {
		before(function() {
			cy.loginAs('bob')
			cy.visit('/apps/collectives/Preexisting%20Collective')
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Preexisting Collective')
		})
		it('Shows the name in the disabled titleform', function() {
			cy.reload()
			cy.get('[data-cy-collectives="page-title-container"] input').should('have.value', 'Preexisting Collective')
		})
	})
})
