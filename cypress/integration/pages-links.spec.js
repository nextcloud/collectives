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
		cy.logout('bob')
		cy.login('bob', { route: '/apps/collectives/Link Testing/Link Source' })
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Link Target')
	})

	const clickLink = function(href, edit) {
		if (edit) {
			// Change to edit mode
			cy.get('button.titleform-button').contains('Edit')
				.click()
			cy.get(`#editor > > .editor__content > .ProseMirror a[href="${href}"]`)
				.click()
		} else {
			cy.get(`#read-only-editor > .ProseMirror a[href="${href}"]`)
				.click()
		}
	}

	// Expected to open file in viewer and stay on same page
	const testLinkToViewer = function(href, fileName, viewerFileElement, edit) {
		clickLink(href, edit)
		cy.location('pathname').should('match', new RegExp(`^${sourceUrl}`))
		cy.get('.modal-title').should('contain', fileName)
		cy.get(`.viewer__content > ${viewerFileElement}.viewer__file`).should('exist')

		cy.get('.modal-header > .icons-menu >> .close-icon')
			.click()
	}

	// Expected to open in same tab
	const testLinkToSameTab = function(href, edit) {
		clickLink(`${Cypress.env('baseUrl')}${href}`, edit)
		cy.location('pathname').should('match', new RegExp(`^${href}`))
		cy.go('back')
	}

	// Expected to open in new tab
	const testLinkToNewTab = function(href, edit, absolute = false) {
		if (!absolute) {
			href = `${Cypress.env('baseUrl')}${href}`
		}
		let openSpy = null
		cy.window().then(win => {
			openSpy = cy.spy(win, 'open').as('redirect')
		})
		clickLink(href, edit)
		cy.get('@redirect')
			.should('be.calledWith', href)
			.then(() => {
				openSpy.restore()
			})

		cy.location('pathname').should('match', new RegExp(`^${sourceUrl}`))
	}

	describe('Link handling', function() {
		// Only run link tests on Nextcloud 24+
		if (!['22', '23'].includes(String(Cypress.env('ncVersion')))) {
			it('Opens link to image in Nextcloud in viewer', function() {
				const href = `/index.php/apps/files/?dir=/&openfile=${imageId}#relPath=//test.png`
				testLinkToViewer(href, 'test.png', 'img', false)
				testLinkToViewer(href, 'test.png', 'img', true)
			})
			it('Opens link to text file in Nextcloud in viewer', function() {
				const href = `/index.php/apps/files/?dir=/&openfile=${textId}#relPath=//test.md`
				testLinkToViewer(href, 'test.md', 'div#editor-container', false)
				testLinkToViewer(href, 'test.md', 'div#editor-container', true)
			})
			it('Opens link to page in this collective in same/new tab depending on view/edit mode', function() {
				const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
				testLinkToSameTab(href, false)
				testLinkToNewTab(href, true)
			})
			it('Opens link to page in other collective in same/new tab depending on view/edit mode', function() {
				const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
				testLinkToSameTab(href, false)
				testLinkToNewTab(href, true)
			})
			it('Opens link to another Nextcloud app in new tab', function() {
				const href = '/index.php/apps/contacts'
				testLinkToNewTab(href, false)
				testLinkToNewTab(href, true)
			})
			it('Opens link to external page in new tab', function() {
				const href = 'http://example.org/'
				testLinkToNewTab(href, false, true)
				testLinkToNewTab(href, true, true)
			})
		}
	})
})
