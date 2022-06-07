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
		cy.login('alice')
		cy.deleteAndSeedCollective('PermissionCollective')
		cy.seedPage('SecondPage', '', 'Readme.md')
		cy.seedCollectivePermissions('PermissionCollective', 'edit', 4)
		cy.seedCircleMember('PermissionCollective', 'bob')
		cy.logout()
	})

	describe('in read-only collective', function() {
		before(function() {
			cy.login('bob', { route: '/apps/collectives/PermissionCollective/SecondPage' })
		})
		it('not able to edit collective', function() {
			cy.get('#titleform input').should('have.attr', 'disabled')
			cy.get('button.edit-button').should('not.exist')
			cy.get('.app-content-list-item.toplevel')
				.get('button.icon.add').should('not.exist')
			cy.get('#editor-container').should('not.exist')
		})
	})
})
