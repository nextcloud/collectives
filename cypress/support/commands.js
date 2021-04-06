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

Cypress.Commands.add('seedCollective', (name) => {
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch('newCollective', { name })
				.catch(e => {
					if (e.request && e.request.status === 422) {
						// The collective already existed... carry on.
					} else {
						throw e
					}
				})
		})
})

Cypress.Commands.add('deleteCollective', (name) => {
	cy.window()
		.its('app')
		.then(async app => {
			const id = app.$store.getters.collectives.find(c => c.name === name).id
			await app.$store.dispatch('trashCollective', { id })
			await app.$store.dispatch('deleteCollective', { id, circle: true })
		})
})

Cypress.Commands.add('seedCircle', (name) => {
	cy.visit('/apps/circles')
	cy.window()
		.its('OCA.Circles.api')
		.then(async api => {
			api.createCircle(4, name)
		})
})

Cypress.Commands.add('createCollective', (name) => {
	cy.get('a [title="Create new collective"]').click()
	cy.get('.collective-create input[type="text"]').type(`${name}{enter}`)
})

Cypress.Commands.add('addGroupToCollective', ({ group, collective }) => {
	cy.visit('/apps/circles')
	cy.get('#circle-navigation .circle .title')
		.contains(collective).click()
	cy.get('#circle-actions-group').click()
	cy.get('input#linkgroup').type(`${group}{enter}`)
	cy.get('#groupslist_table .groupid').should('contain', group)
})
