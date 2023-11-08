import { login, logout } from '@nextcloud/cypress/commands'
import { User } from '@nextcloud/cypress'

import {
	GET_COLLECTIVES,
	GET_TRASH_COLLECTIVES,
	NEW_COLLECTIVE,
	TRASH_COLLECTIVE,
	DELETE_COLLECTIVE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
	UPDATE_COLLECTIVE_PAGE_MODE,
	GET_PAGES,
	NEW_PAGE,
	GET_CIRCLES,
} from '../../src/store/actions.js'
import axios from '@nextcloud/axios'

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

/**
 * Ignore ResizeObserver loop limit exceeded' exceptions from browser
 * See https://stackoverflow.com/q/49384120 for details
 */
const resizeObserverLoopErrRe = /^[^(ResizeObserver loop limit exceeded)]/
Cypress.on('uncaught:exception', (err) => {
	/* returning false here prevents Cypress from failing the test */
	if (resizeObserverLoopErrRe.test(err.message)) {
		return false
	}
})

// Authentication commands from @nextcloud/cypress
Cypress.Commands.add('login', login)
Cypress.Commands.add('logout', logout)

/**
 * Login with the given username to Nextcloud
 */
Cypress.Commands.add('loginAs', (user, password = null) => {
	password ||= user
	const u = new User(user, password)
	cy.login(u)
})

/**
 * Get the editor component
 */
Cypress.Commands.add('getEditor', (timeout = null) => {
	timeout = timeout ?? Cypress.config('defaultCommandTimeout')
	cy.get('[data-collectives-el="editor"]', { timeout })
})

/**
 * Get the ReadOnlyEditor/RichTextReader component
 */
Cypress.Commands.add('getReadOnlyEditor', () => {
	cy.get('[data-collectives-el="reader"]')
})

/**
 * Switch page mode to view or edit
 */
Cypress.Commands.add('switchToViewMode', () => {
	cy.log('Switch to view mode')
	cy.get('button.titleform-button')
		.should('contain', 'Done')
		.click()
	cy.getReadOnlyEditor()
		.should('be.visible')
})

Cypress.Commands.add('switchToEditMode', () => {
	cy.log('Switch to edit mode')
	cy.get('button.titleform-button')
		.should('contain', 'Edit')
		.click()
	cy.getEditor()
		.should('be.visible')
})

/**
 * Enable/disable a Nextcloud app
 */
Cypress.Commands.add('enableApp', appName => cy.setAppEnabled(appName))
Cypress.Commands.add('disableApp', appName => cy.setAppEnabled(appName, false))
Cypress.Commands.add('setAppEnabled', (appName, value = true) => {
	cy.request('/csrftoken').then(({ body }) => {
		const requesttoken = body.token
		const verb = value ? 'enable' : 'disable'
		const api = `${Cypress.env('baseUrl')}/index.php/settings/apps/${verb}`
		return axios.post(api,
			{ appIds: [appName] },
			{ headers: { requesttoken } },
		)
	})
})

/**
 * Enable dashboard widget
 */
Cypress.Commands.add('enableDashboardWidget', (widgetName) => {
	cy.request('/csrftoken').then(({ body }) => {
		const requesttoken = body.token
		const api = `${Cypress.env('baseUrl')}/index.php/apps/dashboard/layout`
		return axios.post(api,
			{ layout: widgetName },
			{ headers: { requesttoken } },
		)
	})
})

/**
 * Create a fresh collective for use in the test
 *
 * If the collective already existed it will be deleted first.
 */
Cypress.Commands.add('deleteAndSeedCollective', (name) => {
	cy.deleteCollective(name)
	cy.log(`Seeding collective ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(NEW_COLLECTIVE, { name })
			const updatedCollectivePath = app.$store.getters.updatedCollectivePath
			app.$router.push(updatedCollectivePath)
		})
	// Make sure new collective is loaded
	cy.get('#titleform input').should('have.value', name)
})

/**
 * Create a collective via UI
 */
Cypress.Commands.add('createCollective', (name, members = []) => {
	cy.log(`Creating collective ${name}`)
	cy.get('button').contains('New collective').click()
	cy.get('.collective-name input[type="text"]').type(`${name}{enter}`)
	if (members.length > 0) {
		for (const member of members) {
			cy.get('.member-picker input[type="text"]').clear()
			cy.get('.member-picker input[type="text"]').type(`${member}`)
			cy.get('.member-search-results .member-row').contains(member).click()
			cy.get('.selected-members .user-bubble__content').should('contain', member)
		}
	}
	cy.get('button').contains('Create').click()
})

/**
 * Delete a collective if it exists
 */
Cypress.Commands.add('deleteCollective', (name) => {
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_COLLECTIVES)
			const id = app.$store.state.collectives.collectives.find(c => c.name === name)?.id
			if (id) {
				cy.log(`Deleting collective ${name} (id ${id})`)
				await app.$store.dispatch(TRASH_COLLECTIVE, { id })
				return await app.$store.dispatch(DELETE_COLLECTIVE, { id, circle: true })
			}

			// Try to find and delete collective from trash
			await app.$store.dispatch(GET_TRASH_COLLECTIVES)
			const trashId = app.$store.state.collectives.trashCollectives.find(c => c.name === name)?.id
			if (trashId) {
				cy.log(`Deleting trashed collective ${name} (id ${trashId})`)
				return await app.$store.dispatch(DELETE_COLLECTIVE, { id: trashId, circle: true })
			}
		})
})

/**
 * Change permission settings for a collective
 */
Cypress.Commands.add('seedCollectivePermissions', (name, type, level) => {
	cy.log(`Seeding collective permissions for ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			const id = app.$store.state.collectives.collectives.find(c => c.name === name).id
			if (type === 'edit') {
				await app.$store.dispatch(UPDATE_COLLECTIVE_EDIT_PERMISSIONS, { id, level })
			} else if (type === 'share') {
				await app.$store.dispatch(UPDATE_COLLECTIVE_SHARE_PERMISSIONS, { id, level })
			}
		})
})

/**
 * Change default page mode for a collective
 */
Cypress.Commands.add('seedCollectivePageMode', (name, mode) => {
	cy.log(`Seeding collective page mode for ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			const id = app.$store.state.collectives.collectives.find(c => c.name === name).id
			await app.$store.dispatch(UPDATE_COLLECTIVE_PAGE_MODE, { id, mode })
		})
})

/**
 * Add a page to a collective
 */
Cypress.Commands.add('seedPage', (name, parentFilePath, parentFileName) => {
	cy.log(`Seeding collective page ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_PAGES)
			const parentPage = app.$store.state.pages.pages.find(function(p) {
				return p.filePath === parentFilePath
					&& p.fileName === parentFileName
			})
			const parentId = parentPage.id
			await app.$store.dispatch(NEW_PAGE, { title: name, pagePath: name, parentId })
			// Return pageId of created page
			return app.$store.state.pages.pages.find(function(p) {
				return p.parentId === parentId
					&& p.title === name
			}).id
		})
})

/**
 * Upload a file
 */
Cypress.Commands.add('uploadFile', (path, mimeType, remotePath = '') => {
	// Get fixture
	return cy.fixture(path, 'base64').then(file => {
		// convert the base64 string to a blob
		const blob = Cypress.Blob.base64StringToBlob(file, mimeType)
		try {
			const file = new File([blob], path, { type: mimeType })
			return cy.window()
				.its('app')
				.then(async app => {
					const response = await axios.put(`${Cypress.env('baseUrl')}/remote.php/webdav/${remotePath}${path}`, file, {
						headers: {
							requesttoken: app.OC.requestToken,
							'Content-Type': mimeType,
						},
					})
					cy.log(`Uploaded file to ${remotePath}${path}`)
					const ocFileId = response.headers['oc-fileid']
					const fileId = parseInt(ocFileId.substring(0, ocFileId.indexOf('oc')))
					return fileId
				})
		} catch (error) {
			cy.log(error)
			throw new Error(`Unable to process file ${path}`)
		}
	})
})

/**
 * Upload content of a page
 */
Cypress.Commands.add('seedPageContent', (pagePath, content) => {
	cy.log(`Seeding collective page content for ${pagePath}`)
	cy.window()
		.its('app')
		.then(async app => {
			await axios.put(`${Cypress.env('baseUrl')}/remote.php/webdav/Collectives/${pagePath}`, content, {
				headers: {
					requesttoken: app.OC.requestToken,
					'Content-Type': 'text/markdown',
				},
			})
		})
})

/**
 * Create a circle (optionally with given config)
 */
Cypress.Commands.add('seedCircle', (name, config = null) => {
	cy.log(`Seeding circle ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_CIRCLES)
			const circle = app.$store.state.circles.circles.find(c => c.sanitizedName === name)
			const api = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles`
			let circleId
			if (!circle) {
				const response = await axios.post(api,
					{ name, personal: false, local: true },
					{ headers: { requesttoken: app.OC.requestToken } },
				)
				circleId = response.data.ocs.data.id
			} else {
				circleId = circle.id
			}
			if (config) {
				// For now we only set the visibility
				const bits = [
					['visible', 8],
					['open', 16],
				]
				const value = bits
					.filter(([k, v]) => config[k])
					.reduce((sum, [k, v]) => sum + v, 0)
				await axios.put(`${api}/${circleId}/config`,
					{ value },
					{ headers: { requesttoken: app.OC.requestToken } },
				)
			}
		})
})

/**
 * Add someone to a circle
 */
Cypress.Commands.add('seedCircleMember', (name, userId, type = 1, level) => {
	cy.log(`Seeding circle member ${name} of type ${type}`)
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_CIRCLES)
			const circleId = app.$store.state.circles.circles.find(c => c.sanitizedName === name).id
			cy.log(`circleId: ${circleId}`)
			const api = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles/${circleId}/members`
			const response = await axios.post(api,
				{ userId, type },
				{ headers: { requesttoken: app.OC.requestToken } },
			).catch(e => {
				if (e.request && e.request.status === 400) {
					// The member already got added... carry on.
				} else {
					throw e
				}
			})
			if (level) {
				const memberId = response.data.ocs.data.id
				cy.log(memberId)
				cy.log(`Setting circle ${name} member ${userId} level to ${level}`)
				await axios.put(`${api}/${memberId}/level`,
					{ level },
					{ headers: { requesttoken: app.OC.requestToken } },
				)
			}
		})
})

/**
 * Fail the test on the initial run to check if retries work
 */
Cypress.Commands.add('testRetry', () => {
	cy.wrap(cy.state('test').currentRetry())
		.should('be.equal', 2)
})
