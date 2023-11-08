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
const silent = { log: false }

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
	const verb = value ? 'enable' : 'disable'
	const api = `${Cypress.env('baseUrl')}/index.php/settings/apps/${verb}`
	return axios.post(api,
		{ appIds: [appName] },
	)
})

/**
 * Enable dashboard widget
 */
Cypress.Commands.add('enableDashboardWidget', (widgetName) => {
	const api = `${Cypress.env('baseUrl')}/index.php/apps/dashboard/layout`
	return axios.post(api,
		{ layout: widgetName },
	)
})

Cypress.Commands.add('store', (selector, options = {}) => {
	if (selector) {
		Cypress.log()
	}
	if (selector) {
		cy.window(silent)
			.its(`app.$store.${selector}`, silent)
	} else {
		cy.window(silent)
			.its('app.$store', silent)
	}
})

Cypress.Commands.add('routeTo', (path) => {
	Cypress.log()
	cy.window(silent)
		.its('app.$router', silent)
		.invoke(silent, 'push', path)
})

Cypress.Commands.add('dispatch', (...args) => {
	Cypress.log()
	cy.store()
		.invoke(silent, ...['dispatch', ...args])
})

/**
 * Create a fresh collective for use in the test
 *
 * If the collective already existed it will be deleted first.
 */
Cypress.Commands.add('deleteAndSeedCollective', (name) => {
	cy.deleteCollective(name)
	cy.log(`Seeding collective ${name}`)
	cy.dispatch(NEW_COLLECTIVE, { name })
	cy.store('getters.updatedCollectivePath')
		.then(path => cy.routeTo(path))
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
	cy.dispatch(GET_COLLECTIVES)
	cy.store('state.collectives.collectives')
		.invoke('find', c => c.name === name)
		.then(collective => collective?.id)
		.then(id => {
			if (id) {
				cy.log(`Deleting collective ${name} (id ${id})`)
				cy.dispatch(TRASH_COLLECTIVE, { id })
				cy.dispatch(DELETE_COLLECTIVE, { id, circle: true })
			} else {
				// Try to find and delete collective from trash
				cy.dispatch(GET_TRASH_COLLECTIVES)
				cy.store('state.collectives.collectives')
					.invoke('find', c => c.name === name)
					.then(collective => collective?.id)
					.then(trashId => {
						if (trashId) {
							cy.log(`Deleting trashed collective ${name} (id ${trashId})`)
							cy.dispatch(DELETE_COLLECTIVE, { id: trashId, circle: true })
						}
					})
			}
		})
})

/**
 * Change permission settings for a collective
 */
Cypress.Commands.add('seedCollectivePermissions', (name, type, level) => {
	const action = (type === 'edit')
		? UPDATE_COLLECTIVE_EDIT_PERMISSIONS
		: UPDATE_COLLECTIVE_SHARE_PERMISSIONS
	cy.log(`Seeding collective permissions for ${name}`)
	cy.store('state.collectives.collectives')
		.invoke('find', c => c.name === name)
		.then(({ id }) => cy.dispatch(action, { id, level }))
})

/**
 * Change default page mode for a collective
 */
Cypress.Commands.add('seedCollectivePageMode', (name, mode) => {
	cy.log(`Seeding collective page mode for ${name}`)
	cy.store('state.collectives.collectives')
		.invoke('find', c => c.name === name)
		.then(({ id }) => cy.dispatch(UPDATE_COLLECTIVE_PAGE_MODE, { id, mode }))
})

/**
 * Add a page to a collective
 */
Cypress.Commands.add('seedPage', (name, parentFilePath, parentFileName) => {
	cy.log(`Seeding collective page ${name}`)
	cy.dispatch(GET_PAGES)
	cy.store('state.pages.pages')
		.invoke('find', function(p) {
			return p.filePath === parentFilePath
				&& p.fileName === parentFileName
		})
		.its('id')
		.then(parentId => {
			cy.dispatch(NEW_PAGE, { title: name, pagePath: name, parentId })
			// Return pageId of created page
			return cy.store('state.pages.pages')
				.invoke('find', p => p.parentId === parentId && p.title === name)
				.its('id')
		})
})

/**
 * Upload a file
 */
Cypress.Commands.add('uploadFile', (path, mimeType, remotePath = '') => {
	Cypress.log()
	// Get fixture
	return cy.fixture(path, 'base64').then(file => {
		// convert the base64 string to a blob
		const blob = Cypress.Blob.base64StringToBlob(file, mimeType)
		try {
			const file = new File([blob], path, { type: mimeType })
			return cy.uploadContent(remotePath + path, file, mimeType)
				.then(response => {
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
	cy.uploadContent(`Collectives/${pagePath}`, content)
})

/**
 * Generic upload of content - used by seedPageContent and uploadPage
 */
Cypress.Commands.add('uploadContent', (path, content, mimetype = 'text/markdown') => {
	// @nextcloud/axios automatic handling for request tokens does not work for webdav
	cy.window()
		.its('app.OC.requestToken')
		.then(requesttoken => {
			const url = `${Cypress.env('baseUrl')}/remote.php/webdav/${path}`
			return axios.put(url, content, {
				headers: {
					requesttoken,
					'Content-Type': mimetype,
				},
			})
		})
})

/**
 * Create a circle (optionally with given config)
 */
Cypress.Commands.add('seedCircle', (name, config = null) => {
	cy.log(`Seeding circle ${name}`)
	cy.dispatch(GET_CIRCLES)
	cy.store('state.circles.circles')
		.invoke('find', c => c.sanitizedName === name)
		.then(async circle => {
			const api = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles`
			let circleId
			if (!circle) {
				const response = await axios.post(api,
					{ name, personal: false, local: true },
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
				)
			}
		})
})

/**
 * Add someone to a circle
 */
Cypress.Commands.add('seedCircleMember', (name, userId, type = 1, level) => {
	cy.log(`Seeding circle member ${name} of type ${type}`)
	cy.dispatch(GET_CIRCLES)
	cy.store('state.circles.circles')
		.invoke('find', c => c.sanitizedName === name)
		.its('id')
		.then(async circleId => {
			cy.log(`circleId: ${circleId}`)
			const api = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles/${circleId}/members`
			const response = await axios.post(api,
				{ userId, type },
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
