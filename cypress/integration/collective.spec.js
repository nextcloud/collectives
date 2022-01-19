/**
 * @copyright Copyright (c) 2021 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
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
 *  Tests for basic Collectives functionality.
 */

describe('Collective', function() {

	before(function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.seedCollective('Preexisting Collective')
		cy.seedCircle('Preexisting Circle')
		cy.seedCircle('History Club', { visible: true, open: true })
		cy.login('jane', 'jane', '/apps/collectives')
		cy.seedCircle('Foreign Circle', { visible: true, open: true })
	})

	describe('in the files app', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/files')
		})
		it('has a matching folder', function() {
			cy.get('#fileList').should('contain', 'Collectives')
			cy.get('#fileList a').contains('Collectives').click()
			cy.get('#controls .breadcrumb').should('contain', 'Collectives')
			cy.get('#fileList').should('contain', 'Preexisting Collective')
			cy.get('#fileList a').contains('Preexisting Collective').click()
			cy.get('#controls .breadcrumb').should('contain', 'Preexisting Collective')
			cy.get('#fileList').should('contain', 'Readme.md')
		})
	})

	describe('in the contacts app', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/contacts')
		})
		it('has a matching circle', function() {
			cy.contains('.app-navigation-entry a',
				'Preexisting Collective',
				{ timeout: 8000 }
			).click()
			cy.get('.contact-header h2 input').should('have.value', 'Preexisting Collective')
			cy.get('.members-list').should('contain', 'bob')
		})
	})

	describe('name conflicts', function() {
		it('Reports existing circle', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective('Foreign Circle')
			cy.get('main .empty-content').should('contain', 'build shared knowledge')
			cy.get('.toast-warning').should('contain', 'Could not create the collective')
			cy.get('.toast-warning').should('contain', 'A circle with that name exists')
		})
		it('Reports existing collective', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective('Preexisting Collective')
			cy.get('main .empty-content').should('contain', 'build shared knowledge')
			cy.get('.toast-warning').should('contain', 'Could not create the collective')
			cy.get('.toast-warning').should('contain', 'Collective already exists')
		})
		it('creates collectives by picking circle',
			function() {
				cy.login('bob', 'bob', '/apps/collectives')
				cy.get('button.icon-circles').click()
				cy.get('.multiselect__option').should('not.contain', 'Foreign')
				cy.get('.multiselect__option [title*=History]').click()
				cy.get('input.icon-confirm').click()
				cy.get('#titleform input').should('have.value', 'History Club')
				cy.get('.toast-info').should('contain',
					'Created collective "History Club" for existing circle.'
				)
			})
		it('collectives of visible circles only show for members',
			function() {
				cy.login('jane', 'jane', '/apps/collectives')
				cy.get('button.icon-circles')
				cy.get('.app-navigation-entry').should('not.contain', 'History Club')
			})
		it('creates collectives for admins of corresponding circle',
			function() {
				cy.login('bob', 'bob', '/apps/collectives')
				cy.createCollective('Preexisting Circle')
				cy.get('#titleform input').should('have.value', 'Preexisting Circle')
				cy.get('.toast-info').should('contain',
					'Created collective "Preexisting Circle" for existing circle.'
				)
			})
		after(function() {
			cy.deleteCollective('Preexisting Circle')
			cy.deleteCollective('History Club')
		})
	})

	describe('non ascii characters', function() {
		const special = 'stupid !@#$%^&()_ special chars'

		it('can handle special chars in collective name',
			function() {
				cy.login('bob', 'bob', '/apps/collectives')
				cy.createCollective(special)
				cy.get('#titleform input').should('have.value', special)
			})

		after(function() {
			cy.deleteCollective(special)
		})
	})
	// Note: the different assertions in here
	// all happen without any page reload or navigation.
	//
	// Cookies are cleared after every test.
	// So in all but the first run it block
	// bob will be logged out.
	describe('after creation', function() {
		const name = 'Created just now ' + Math.random().toString(36).substr(2, 4)
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.createCollective(name)
		})
		it('Shows the name in the disabled titleform', function() {
			cy.get('#titleform input').should('have.value', `${name}`)
			cy.get('#titleform input').should('have.attr', 'disabled')
		})
		it('Has an initial Readme.md', function() {
			cy.get('#text h1').should('contain', 'Welcome to your new collective')
		})
		it('Allows creation of pages', function() {
			cy.get('.app-content-list-item')
				.trigger('mouseover')
			cy.get('.app-content-list button.icon-add')
				.should('contain', 'Add a page')
		})
	})

	describe('reloading works', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/Preexisting%20Collective')
			cy.get('#titleform input').should('have.value', 'Preexisting Collective')
		})
		it('Shows the name in the disabled titleform', function() {
			cy.reload()
			cy.get('#titleform input').should('have.value', 'Preexisting Collective')
		})
	})
})
