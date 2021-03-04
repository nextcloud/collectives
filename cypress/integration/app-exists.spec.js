/**
 * @copyright Copyright (c) 2021 Azul <azul@riseup.net>
 *
 * @author Azul <azul@riseup.net>
 *
 * @license GNU AGPL version 3 or any later version
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

describe('The app is properly installed and responding', function() {
	it('shows up in the app list', function() {
		cy.login('admin', 'admin', 'settings/apps/installed/collectives')
		cy.get('#app-sidebar-vue .app-details input.enable')
			.should('have.value', 'Disable')
	})

	it('allows creating a new collective', function() {
		cy.login('jane', 'jane', '/apps/collectives')
		cy.get('#app-navigation-vue')
			.should('contain', 'Create new collective')
	})
})
