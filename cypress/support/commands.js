import {
	GET_COLLECTIVES,
	NEW_COLLECTIVE,
	TRASH_COLLECTIVE,
	DELETE_COLLECTIVE,
	UPDATE_COLLECTIVE_EDIT_PERMISSIONS,
	UPDATE_COLLECTIVE_SHARE_PERMISSIONS,
	GET_PAGES,
	NEW_PAGE,
	GET_CIRCLES,
} from '../../src/store/actions.js'
import axios from '@nextcloud/axios'

const url = Cypress.config('baseUrl').replace(/\/index.php\/?$/g, '')
Cypress.env('baseUrl', url)

/**
 * Login a user to Nextcloud and visit a given route
 */
Cypress.Commands.add('login', (user, { password, route, onBeforeLoad } = {}) => {
	route = route || '/apps/collectives'
	password = password || user
	cy.session(user, function() {
		cy.visit(route)
		cy.get('input[name=user]').type(user)
		cy.get('input[name=password]').type(password)
		cy.get('form[name=login] [type=submit]').click()
		cy.url().should('not.include', 'index.php/login?redirect_url')
		cy.url().should('include', route.replaceAll(' ', '%20'))
	})
	// in case the session already existed but we are on a different route...
	cy.visit(route, { onBeforeLoad })
})

/**
 * Logout a user from Nextcloud
 */
Cypress.Commands.add('logout', () => {
	cy.session('_guest', function() {
	})
})

/**
 * Enable/disable a Nextcloud app
 */
Cypress.Commands.add('enableApp', appName => cy.setAppEnabled(appName))
Cypress.Commands.add('disableApp', appName => cy.setAppEnabled(appName, false))
Cypress.Commands.add('setAppEnabled', (appName, value = true) => {
	cy.window().then(async win => {
		const verb = value ? 'enable' : 'disable'
		const api = `${Cypress.env('baseUrl')}/index.php/settings/apps/${verb}`
		return axios.post(api,
			{ appIds: [appName] },
			{ headers: { requesttoken: win.OC.requestToken } },
		)
	})
})

/**
 * First delete, then seed a collective (to start fresh)
 */
Cypress.Commands.add('deleteAndSeedCollective', (name) => {
	cy.deleteCollective(name)
	cy.seedCollective(name)
})

/**
 * Create a collective if it doesn't exist
 */
Cypress.Commands.add('seedCollective', (name) => {
	cy.log(`Seeding collective ${name}`)
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
			const updatedCollectivePath = app.$store.getters.updatedCollectivePath
			if (updatedCollectivePath) {
				app.$router.push(updatedCollectivePath)
			} else {
				// Fallback - if collective exists, updatedCollectivePath is undefined
				app.$router.push(`/${name}`)
			}
		})
})

/**
 * Create a collective via UI
 */
Cypress.Commands.add('createCollective', (name) => {
	cy.log(`Creating collective ${name}`)
	cy.get('a [title="Create new collective"]').click()
	cy.get('.collective-create input[type="text"]').type(`${name}{enter}`)
})

/**
 * Delete a collective if it exists
 */
Cypress.Commands.add('deleteCollective', (name) => {
	cy.log(`Deleting collective ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_COLLECTIVES)
			const id = app.$store.state.collectives.collectives.find(c => c.name === name)?.id
			if (id) {
				await app.$store.dispatch(TRASH_COLLECTIVE, { id })
				await app.$store.dispatch(DELETE_COLLECTIVE, { id, circle: true })
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
 * Add a page to a collective
 */
Cypress.Commands.add('seedPage', (name, parentFilePath, parentFileName) => {
	cy.log(`Seeding collective page ${name}`)
	cy.window()
		.its('app')
		.then(async app => {
			await app.$store.dispatch(GET_PAGES)
			const parentId = app.$store.state.pages.pages.find(function(p) {
				return p.filePath === parentFilePath
					&& p.fileName === parentFileName
			}).id
			await app.$store.dispatch(NEW_PAGE, { title: name, pagePath: name, parentId })
		})
})

/**
 * Upload a file
 */
Cypress.Commands.add('uploadFile', (path, mimeType) => {
	// Get fixture
	return cy.fixture(path, 'base64').then(file => {
		// convert the base64 string to a blob
		const blob = Cypress.Blob.base64StringToBlob(file, mimeType)
		try {
			const file = new File([blob], path, { type: mimeType })
			return cy.window()
				.its('app')
				.then(async app => {
					const response = await axios.put(`${Cypress.env('baseUrl')}/remote.php/webdav/${path}`, file, {
						headers: {
							requesttoken: app.OC.requestToken,
							'Content-Type': mimeType,
						},
					})
					cy.log(`Uploaded file to ${path}`)
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
	cy.visit('/apps/collectives')
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
