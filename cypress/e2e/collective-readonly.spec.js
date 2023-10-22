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

describe('Read-only collective', function() {

	before(function() {
		cy.loginAs('alice')
		cy.visit('apps/collectives')
		cy.deleteAndSeedCollective('PermissionCollective')
		cy.seedPage('SecondPage', '', 'Readme.md')
		cy.seedCollectivePermissions('PermissionCollective', 'edit', 4)
		cy.seedCircleMember('PermissionCollective', 'bob')
	})

	describe('in read-only collective', function() {
		beforeEach(function() {
			cy.loginAs('bob')
			cy.visit('/apps/collectives/PermissionCollective/SecondPage')
		})

		it('not able to edit collective', function() {
			cy.get('#titleform input').should('have.attr', 'disabled')
			cy.get('button.titleform-button').should('not.exist')
			cy.get('.app-content-list-item.toplevel')
				.find('button.icon.add')
				.should('not.exist')
			cy.getEditor()
				.should('not.exist')
		})

		it('actions menu with outline toggle is there', function() {
			cy.get('#titleform button.action-item__menutoggle')
				.click()
			cy.get('button.action-button')
				.contains('Show outline')
		})
	})
})
