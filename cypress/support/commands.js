/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { logout } from '@nextcloud/cypress/commands'
import { User } from '@nextcloud/cypress'

import * as api from '../../src/apis/collectives/index.js'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)
const silent = { log: false }

// Prevent @nextcloud/router from reading window.location
window._oc_webroot = ''

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

// Copy of the new login command as long as we are blocked to upgrade @nextcloud/cypress by cypress crashes
const login = function(user) {
	cy.session(user, function() {
		cy.request('/csrftoken').then(({ body }) => {
			const requestToken = body.token
			cy.request({
				method: 'POST',
				url: '/login',
				body: {
					user: user.userId,
					password: user.password,
					requesttoken: requestToken,
				},
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					// Add the Origin header so that the request is not blocked by the browser.
					Origin: (Cypress.config('baseUrl') ?? '').replace('index.php/', ''),
				},
				followRedirect: false,
			})
		})
	}, {
		validate() {
			cy.request('/apps/files').its('status').should('eq', 200)
		},
	})
}

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
	return cy.get('[data-cy-collectives="editor"]', { timeout })
})

/**
 * Get the ReadOnlyEditor/RichTextReader component
 */
Cypress.Commands.add('getReadOnlyEditor', () => {
	return cy.get('[data-cy-collectives="reader"]')
})

Cypress.Commands.add('getEditorContent', (edit = false) => {
	return (edit ? cy.getEditor() : cy.getReadOnlyEditor())
		.should('be.visible')
		.find('.ProseMirror')
})

/**
 * Switch page mode to view or edit
 */
Cypress.Commands.add('switchToViewMode', () => {
	Cypress.log()
	cy.get('button.titleform-button')
		.should('contain', 'Done')
		.click()
	cy.getReadOnlyEditor()
		.should('be.visible')
})

Cypress.Commands.add('switchToEditMode', () => {
	Cypress.log()
	cy.get('button.titleform-button')
		.should('contain', 'Edit')
		.click()
	cy.getEditor()
		.should('be.visible')
})

/**
 * Enable/disable a Nextcloud app
 */
Cypress.Commands.add('enableApp', appName => {
	Cypress.log()
	cy.setAppEnabled(appName)
})

Cypress.Commands.add('disableApp', appName => {
	Cypress.log()
	cy.setAppEnabled(appName, false)
})

Cypress.Commands.add('setAppEnabled', (appName, value = true) => {
	const verb = value ? 'enable' : 'disable'
	const url = `${Cypress.env('baseUrl')}/index.php/settings/apps/${verb}`
	return axios.post(url,
		{ appIds: [appName] },
	)
})

/**
 * Enable dashboard widget
 */
Cypress.Commands.add('enableDashboardWidget', (widgetName) => {
	Cypress.log()
	const url = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/dashboard/api/v3/layout`
	return axios.post(url,
		{ layout: [widgetName] },
	)
})

Cypress.Commands.add('routeTo', (path) => {
	Cypress.log()
	cy.window(silent)
		.its('app.$router', silent)
		.invoke(silent, 'push', path)
})

Cypress.Commands.add('findBy',
	{ prevSubject: true },
	(subject, properties) => {
		Cypress.log()
		return subject.find(item => {
			for (const key in properties) {
				if (item[key] !== properties[key]) {
					return false
				}
			}
			return true
		}) || null
	})

/**
 * Create a fresh collective for use in the test
 *
 * If the collective already existed it will be deleted first.
 */
Cypress.Commands.add('deleteAndSeedCollective', (name) => {
	Cypress.log()
	cy.deleteCollective(name)
	cy.seedCollective(name)
	cy.getCollectives()
		.findBy({ name })
})

Cypress.Commands.add(
	'seedCollective',
	name => api.newCollective({ name }),
)

/**
 * Create a collective via UI
 */
Cypress.Commands.add('createCollective', (name, members = []) => {
	Cypress.log()
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
 * Delete a collective - no matter if it is in use or trashed.
 *
 * This command will succeed if the collective does not exist at all.
 */
Cypress.Commands.add('deleteCollective', (name) => {
	cy.trashCollective(name)
	cy.deleteCollectiveFromTrash(name)
})

Cypress.Commands.add('getCollectives', () => {
	return api.getCollectives()
		.then(response => response.data.ocs.data.collectives)
})

Cypress.Commands.add('getCollectivesFolder', () => {
	return api.getCollectivesFolder()
		.then(response => response.data.ocs.data.user_folder)
})

Cypress.Commands.add('setCollectivesFolder', api.setCollectivesFolder)

/**
 * Move a collective into the trash if it exists.
 *
 * This command will succeed if the collective does not exist at all.
 */
Cypress.Commands.add('trashCollective', (name) => {
	cy.getCollectives()
		.findBy({ name })
		.then((found) => found && api.trashCollective(found.id))
})

Cypress.Commands.add('getTrashCollectives', () => {
	return api.getTrashCollectives()
		.then(response => response.data.ocs.data.collectives)
})

/**
 * Clear a collective from the trash if it is in there.
 *
 * This command will succeed if the collective does not exist at all.
 */
Cypress.Commands.add('deleteCollectiveFromTrash', (name) => {
	cy.getTrashCollectives()
		.findBy({ name })
		.then((found) => found && api.deleteCollective(found.id, true))
})

/**
 * Change permission settings for a collective
 */
Cypress.Commands.add('seedCollectivePermissions', (name, type, level) => {
	Cypress.log()
	const action = (type === 'edit')
		? api.updateCollectiveEditPermissions
		: api.updateCollectiveSharePermissions
	cy.getCollectives()
		.findBy({ name })
		.then((found) => action(found.id, level))
})

/**
 * Change default page mode for a collective
 */
Cypress.Commands.add('seedCollectivePageMode', (name, mode) => {
	Cypress.log()
	cy.getCollectives()
		.findBy({ name })
		.then((found) => api.updateCollectivePageMode(found.id, mode))
})

/**
 * Context for the given collective for a logged in user.
 *
 * @param {object} collective - Collective to provide the context for.
 */
function collectiveContext(collective) {
	return {
		isPublic: false,
		collectiveId: collective.id,
		shareTokenParam: null,
	}
}

Cypress.Commands.add('getPages', collective => {
	return api.getPages(collectiveContext(collective))
		.then(response => response.data.ocs.data.pages)
})

/**
 * Add a page to a collective
 */
Cypress.Commands.add('seedPage',
	{ prevSubject: true },
	(subject, name, parentFilePath = '', parentFileName = 'Readme.md') => {
		Cypress.log()
		cy.getPages(subject)
			.findBy({ filePath: parentFilePath, fileName: parentFileName })
			.then(({ id: parentId }) => {
				return api.createPage(
					collectiveContext(subject),
					{ parentId, title: name, pagePath: name },
				)
			})
			.its('data.ocs.data.page.id')
			.then(pageId => ({ ...subject, collectiveId: collectiveContext(subject).collectiveId, pageId }))
	})

/**
 * Upload content of a page
 */
Cypress.Commands.add('seedPageContent', (pagePath, content) => {
	const contentForLog = content.length > 200
		? content.substring(0, 200) + '…'
		: content
	Cypress.log({ message: `${pagePath}, ${contentForLog}` })
	cy.uploadContent(`Collectives/${pagePath}`, content)
})

Cypress.Commands.add('uploadFile', (path, mimeType, remotePath = '') => {
	Cypress.log()
	// Get fixture
	return cy.fixture(path, 'base64').then(data => {
		// convert the base64 string to a blob
		const blob = Cypress.Blob.base64StringToBlob(data, mimeType)
		const file = new File([blob], path, { type: mimeType })
		return cy.uploadContent(remotePath + path, file, mimeType)
			.then(response => {
				const ocFileId = response.headers['oc-fileid']
				const fileId = parseInt(ocFileId.substring(0, ocFileId.indexOf('oc')))
				return fileId
			})
	})
})

/**
 * Generic upload of content - used by seedPageContent and uploadPage
 */
Cypress.Commands.add('uploadContent', (path, content, mimetype = 'text/markdown') => {
	// @nextcloud/axios automatic handling for request tokens does not work for webdav
	cy.request('/csrftoken').then(({ body }) => {
		const requesttoken = body.token
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
 * Create a team (optionally with given config)
 */
Cypress.Commands.add('seedCircle', (name, config = null) => {
	Cypress.log()
	cy.circleFind(name)
		.then(async circle => {
			const url = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles`
			let circleId
			if (!circle) {
				const response = await axios.post(url,
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
				await axios.put(`${url}/${circleId}/config`,
					{ value },
				)
			}
		})
})

Cypress.Commands.add('getCircles', () => {
	return axios.get(generateOcsUrl('apps/circles/circles'))
		.then(response => response.data.ocs.data)
})

Cypress.Commands.add('circleFind', (name) => {
	Cypress.log()
	cy.getCircles()
		.findBy({ sanitizedName: name })
})

/**
 * Add someone to a team
 */
Cypress.Commands.add('circleAddMember',
	{ prevSubject: true },
	async ({ id }, userId, type = 1) => {
		Cypress.log()
		const url = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles/${id}/members`
		const response = await axios.post(url,
			{ userId, type },
		)
		const memberId = response.data.ocs.data.id
		return { circleId: id, userId, memberId }
	},
)

Cypress.Commands.add('circleSetMemberLevel',
	{ prevSubject: true },
	({ circleId, memberId }, level) => {
		Cypress.log()
		const url = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/circles/circles/${circleId}/members`
		return axios.put(`${url}/${memberId}/level`,
			{ level },
		)
	},
)

/**
 * Fail the test on the initial run to check if retries work
 */
Cypress.Commands.add('testRetry', () => {
	cy.wrap(cy.state('test').currentRetry())
		.should('be.equal', 2)
})

Cypress.Commands.add('setAppConfig', (app, key, value) => {
	Cypress.log()
	const url = `${Cypress.env('baseUrl')}/ocs/v2.php/apps/provisioning_api/api/v1/config/apps/${app}/${key}`
	return axios.post(url, { value }, {
		headers: {
			'OCS-APIRequest': true,
		},
	})
})
