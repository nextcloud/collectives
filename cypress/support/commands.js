import { NEW_COLLECTIVE, TRASH_COLLECTIVE, DELETE_COLLECTIVE, GET_PAGES, NEW_PAGE }
	from '../../src/store/actions'
import axios from '@nextcloud/axios'

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

Cypress.Commands.add('login', (user, password, route = '/apps/files') => {
	cy.clearCookies()
	cy.visit(route)
	cy.get('input[name=user]').type(user)
	cy.get('input[name=password]').type(password)
	cy.get('#submit-wrapper input[type=submit]').click()
	cy.url().should('not.include', 'index.php/login?redirect_url')
	cy.url().should('include', route.replaceAll(' ', '%20'))
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
			await app.$store.dispatch(NEW_COLLECTIVE, { name })
				.catch(e => {
					if (e.request && e.request.status === 422) {
						// The collective already existed... carry on.
					} else {
						throw e
					}
				})
			app.$router.push(app.$store.getters.updatedCollectivePath)
		})
})

Cypress.Commands.add('seedPage', (name, parentFilePath, parentFileName) => {
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_PAGES)
			const parentId = app.$store.state.pages.pages.find(p => (p.filePath === parentFilePath && p.fileName === parentFileName)).id
			await app.$store.dispatch(NEW_PAGE, { title: name, pagePath: name, parentId })
		})
})

Cypress.Commands.add('seedPageContent', (user, pagePath, content) => {
	cy.window()
		.its('app')
		.then(async app => {
			await axios.put(`${Cypress.env('baseUrl')}/remote.php/dav/files/${user}/Collectives/${pagePath}`, content, {
				headers: {
					requesttoken: app.OC.requestToken,
					'Content-Type': 'text/markdown',
				},
			})
		})
})

Cypress.Commands.add('deleteCollective', (name) => {
	cy.window()
		.its('app')
		.then(async app => {
			const id = app.$store.state.collectives.collectives.find(c => c.name === name).id
			await app.$store.dispatch(TRASH_COLLECTIVE, { id })
			await app.$store.dispatch(DELETE_COLLECTIVE, { id, circle: true })
		})
})

Cypress.Commands.add('seedCircle', (name) => {
	cy.visit('/apps/collectives')
	cy.window()
		.its('app')
		.then(async app => {
			await axios.post(
				`${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles`,
				{ name, personal: false, local: true },
				{ headers: { requesttoken: app.OC.requestToken } },
			)
		})
})

Cypress.Commands.add('createCollective', (name) => {
	cy.get('a [title="Create new collective"]').click()
	cy.get('.collective-create input[type="text"]').type(`${name}{enter}`)
})

Cypress.Commands.add('addGroupToCollective', ({ group, collective }) => {
	cy.visit('/apps/contacts')
	cy.contains('.app-navigation-entry a', collective).click()
	cy.get('.app-content-list button.icon-add').click()
	cy.get('.entity-picker input').type(`${group}`)
	cy.get('.user-bubble__title').contains(group).click()
	cy.get('.entity-picker button.primary').click()
	cy.get(`.members-list [user="${group}"] button.action-item__menutoggle `).click()
	cy.contains('.popover .action button', 'Promote to Admin').click()
})
