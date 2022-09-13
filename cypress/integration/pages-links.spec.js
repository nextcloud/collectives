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
 *  Tests for basic Page functionality.
 */

let imageId
let textId
const sourceUrl = '/index.php/apps/collectives/Link%20Testing/Link%20Source'

describe('Page', function() {
	before(function() {
		cy.login('bob', { route: '/apps/collectives' })
		cy.deleteAndSeedCollective('Link Testing')
		cy.seedPage('Link Target', '', 'Readme.md')
		cy.seedPageContent('Link%20Testing/Link%20Target.md', 'Some content')
		cy.seedPage('Link Source', '', 'Readme.md')
		cy.uploadFile('test.md', 'text/markdown').then((id) => {
			textId = id
		}).then(() => {
			cy.uploadFile('test.png', 'image/png').then((id) => {
				imageId = id
			})
		}).then(() => {
			cy.seedPageContent('Link%20Testing/Link%20Source.md', `
## Links supposed to open in viewer

* Relative link to image in Nextcloud: [image](//test.png?fileId=${imageId})
* Relative link to text file in Nextcloud: [test.md](//test.md?fileId=${textId})

## Links supposed to open in same window

* Link to page in this collective: [Link Target](${Cypress.env('baseUrl')}/index.php/apps/collectives/Link%20Testing/Link%20Target)
* Link to page in other collective: [Another Collective/First Page](${Cypress.env('baseUrl')}/index.php/apps/collectives/Another%20Collective/First%20Page)

## Links supposed to open in new window

* Link to another app in Nextcloud: [Contacts](${Cypress.env('baseUrl')}/index.php/apps/contacts)
* Link to external page: [example.org](http://example.org/)
			`)
			cy.deleteAndSeedCollective('Another Collective')
			cy.seedPage('First Page', '', 'Readme.md')
		})
	})

	beforeEach(function() {
		cy.login('bob', { route: '/apps/collectives/Link Testing/Link Source' })
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Link Target')
	})

	const clickLink = function(href, edit) {
		if (edit) {
			// Change to edit mode
			cy.get('button.titleform-button').contains('Edit')
				.click()
			cy.get(`.editor > > .editor__content > .ProseMirror a[href="${href}"]`)
				.click()
		} else {
			cy.get(`#read-only-editor > .ProseMirror a[href="${href}"]`)
				.click()
		}
	}

	// Expected to open file in viewer and stay on same page
	const testLinkToViewer = function(href, { fileName, viewerFileElement, edit = false }) {
		clickLink(href, edit)
		cy.location('pathname').should('match', new RegExp(`^${sourceUrl}`))
		cy.get('.modal-title').should('contain', fileName)
		cy.get(`.viewer__content > ${viewerFileElement}.viewer__file`).should('exist')

		cy.get('.modal-header > .icons-menu > button.header-close')
			.click()
	}

	// Expected to open in same tab
	const testLinkToSameTab = function(href, { edit = false, isPublic = false } = {}) {
		clickLink(`${Cypress.env('baseUrl')}${href}`, edit)
		if (!isPublic) {
			cy.location('pathname').should('match', new RegExp(`^${href}`))
		} else {
			const encodedCollectiveName = encodeURIComponent('Link Testing')
			cy.location('pathname').should('match', new RegExp(`^${href.replace(`/${encodedCollectiveName}`, `/p/\\w+/${encodedCollectiveName}`)}`))
		}
		cy.go('back')
	}

	// Expected to open in new tab
	const testLinkToNewTab = function(href, { edit = false, isPublic = false, absolute = false } = {}) {
		if (!absolute) {
			href = `${Cypress.env('baseUrl')}${href}`
		}
		let openStub = null
		cy.window().then(win => {
			openStub = cy.stub(win, 'open').as('open')
		})
		clickLink(href, edit)
		cy.get('@open')
			.should('be.calledWith', href)
			.then(() => {
				openStub.restore()
			})

		if (!isPublic) {
			cy.location('pathname').should('match', new RegExp(`^${sourceUrl}`))
		} else {
			const encodedCollectiveName = encodeURIComponent('Link Testing')
			cy.location('pathname').should('match', new RegExp(`^${sourceUrl.replace(`/${encodedCollectiveName}`, `/p/\\w+/${encodedCollectiveName}`)}`))
		}
	}

	describe('Link handling internal', function() {
		it('Opens link to image in Nextcloud in viewer', function() {
			const href = `/index.php/apps/files/?dir=/&openfile=${imageId}#relPath=//test.png`
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img' })
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img', edit: true })
		})
		it('Opens link to text file in Nextcloud in viewer', function() {
			const href = `/index.php/apps/files/?dir=/&openfile=${textId}#relPath=//test.md`
			testLinkToViewer(href, { fileName: 'test.md', viewerFileElement: '[data-text-el="editor-container"]' })
			testLinkToViewer(href, { fileName: 'test.md', viewerFileElement: '[data-text-el="editor-container"]', edit: true })
		})
		it('Opens link to page in this collective in same/new tab depending on view/edit mode', function() {
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			testLinkToSameTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link to page in other collective in same/new tab depending on view/edit mode', function() {
			const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
			testLinkToSameTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link to another Nextcloud app in new tab', function() {
			const href = '/index.php/apps/contacts'
			testLinkToNewTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link to external page in new tab', function() {
			const href = 'http://example.org/'
			testLinkToNewTab(href, { absolute: true })
			testLinkToNewTab(href, { edit: true, absolute: true })
		})
	})

	describe('Link handling public share', function() {
		let shareUrl

		it('Share the collective', function() {
			cy.visit('/apps/collectives', {
				onBeforeLoad(win) {
					// navigator.clipboard doesn't exist on HTTP requests (in CI), so let's create it
					if (!win.navigator.clipboard) {
						win.navigator.clipboard = {
							__proto__: {
								writeText: () => {},
							},
						}
					}
					// overwrite navigator.clipboard.writeText with cypress stub
					cy.stub(win.navigator.clipboard, 'writeText', (text) => {
						shareUrl = text
					})
						.as('clipBoardWriteText')
				},
			})
			cy.get('.collectives_list_item')
				.contains('li', 'Link Testing')
				.find('.action-item__menutoggle')
				.click({ force: true })
			cy.intercept('POST', '**/_api/*/share').as('createShare')
			cy.get('button')
				.contains('Share link')
				.click()
			cy.wait('@createShare')
			cy.intercept('PUT', '**/_api/*/share/*').as('updateShare')
			cy.get('input#shareEditable')
				.check({ force: true }).then(() => {
					cy.get('input#shareEditable')
						.should('be.checked')
				})
			cy.wait('@updateShare')
			cy.get('button')
				.contains('Copy share link')
				.click()
			cy.get('@clipBoardWriteText').should('have.been.calledOnce')
		})
		it('Public share: opens link to page in this collective in same/new tab depending on view/edit mode', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			testLinkToSameTab(href, { isPublic: true })
			// testLinkToNewTab(href, { edit: true, isPublic: true })
		})
		it('Public share: opens link to external page in new tab', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = 'http://example.org/'
			testLinkToNewTab(href, { isPublic: true, absolute: true })
			testLinkToNewTab(href, { edit: true, isPublic: true, absolute: true })
		})
	})
})
