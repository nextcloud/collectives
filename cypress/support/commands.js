/**
 * @copyright Copyright (c) 2019 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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

import { addMatchImageSnapshotCommand } from 'cypress-image-snapshot/command'
import axios from '@nextcloud/axios'

addMatchImageSnapshotCommand()

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

Cypress.Commands.add('login', (user, password, route = '/apps/files') => {
	cy.clearCookies()
	cy.visit(route)
	cy.get('input[name=user]').type(user)
	cy.get('input[name=password]').type(password)
	cy.get('#submit-wrapper input[type=submit]').click()
	cy.url().should('not.include', 'index.php/login?redirect_url')
	cy.url().should('include', route)
})

Cypress.Commands.add('logout', () => {
	cy.clearLocalStorage()
	cy.clearCookies()
})

Cypress.Commands.add('toggleApp', (appName) => {
	cy.login('admin', 'admin', `/settings/apps/installed/${appName}`)
	cy.get('#app-sidebar-vue .app-details input.enable').click()
	cy.logout()
})

