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

/**
 *  Tests for Collectives settings.
 */

describe('Collective', function() {
	before(function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.seedCollective('Change me')
	})

	describe('set emoji', function() {
		it('Allows setting an emoji', function() {
			cy.visit('/apps/collectives')
			cy.get('.collectives_list_item')
				.contains('li', 'Change me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button.action-button')
				.contains('Settings')
				.click()
			cy.get('button.emoji')
				.click()
			cy.contains('.emoji-popover span.emoji-mart-emoji', '🥰').click()
			cy.reload()
			cy.contains('.app-navigation-entry', 'Change me')
				.find('.app-navigation-entry-icon').should('contain', '🥰')
		})
	})

	describe('rename collective', function() {
		it('Allows to rename the collective', function() {
			cy.login('bob', 'bob', '/apps/collectives')
			cy.get('.collectives_list_item')
				.contains('li', 'Change me')
				.find('.action-item__menutoggle')
				.click()
			cy.get('button.action-button')
				.contains('Settings')
				.click()
			cy.get('div.collective-name input[type="text"]').type(' now{enter}')
			cy.reload()
			cy.get('.collectives_list_item')
				.should('contain', 'Change me now')
		})
	})
})
