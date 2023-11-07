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

const baseUrl = Cypress.env('baseUrl')
const sourceUrl = new URL(`${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Source`)
let imageId, pdfId, textId
let anotherCollectiveFirstPageId, linkTargetPageId

describe('Page Link Handling', function() {
	before(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives')
		cy.deleteAndSeedCollective('Another Collective')
		cy.seedPage('First Page', '', 'Readme.md').then((id) => {
			anotherCollectiveFirstPageId = id
		})
		cy.deleteAndSeedCollective('Link Testing')
		cy.seedPage('Parent', '', 'Readme.md')
		cy.seedPage('Child', '', 'Parent.md')
		cy.seedPage('Link Target', '', 'Readme.md').then((id) => {
			linkTargetPageId = id
		})
		cy.seedPageContent('Link%20Testing/Link%20Target.md', 'Some content')
		cy.seedPage('Link Source', '', 'Readme.md')
		cy.uploadFile('test.md', 'text/markdown').then((id) => {
			textId = id
		}).then(() => {
			cy.uploadFile('test.png', 'image/png').then((id) => {
				imageId = id
			})
			cy.uploadFile('test.pdf', 'application/pdf', 'Collectives/Link%20Testing/').then((id) => {
				pdfId = id
			})
		}).then(() => {
			cy.seedPageContent('Link%20Testing/Readme.md', `
## Links supposed to open in same window

* Relative path to page in this collective with fileId:  [Link Target](./Link%20Target?fileId=${linkTargetPageId})
			`)
			cy.seedPageContent('Link%20Testing/Parent/Readme.md', `
## Links supposed to open in same window

* Relative path to page in this collective with fileId:  [../Link Target.md](../Link%20Target.md?fileId=${linkTargetPageId})
			`)
			cy.seedPageContent('Link%20Testing/Link%20Source.md', `
## Links supposed to open in viewer

* Absolute path to image in Nextcloud: [image](//test.png?fileId=${imageId})
* Absolute path to text file in Nextcloud: [test.md](//test.md?fileId=${textId})
* Relative path to pdf file in Nextcloud: [test.pdf](test.pdf?fileId=${pdfId})

## Links supposed to open in same window

* URL to page in this collective: [Link Target](${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target)
* Absolute path to page in this collective:  [Link Target](/index.php/apps/collectives/Link%20Testing/Link%20Target)
* Relative path to page in this collective with fileId:  [Link Target](./Link%20Target?fileId=${linkTargetPageId})
* Relative path to page in this collective with fileId and outdated path:  [Link Target](./Link%20Target%20Outdated?fileId=${linkTargetPageId})
* Relative path to page in this collective without fileId:  [Link Target](./Link%20Target)
* Relative path to markdown file in this collective:  [Link Target](./Link%20Target.md)

* URL to page in other collective with fileId: [Another Collective/First Page](${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId})
* Absolute path to page in other collective without fileId: [Another Collective/First Page](/index.php/apps/collectives/Another%20Collective/First%20Page)

## Links supposed to open in new window

* URL to another app in Nextcloud: [Contacts](${baseUrl}/index.php/apps/contacts)
* Absolute path to another app in Nextcloud: [Contacts](/index.php/apps/contacts)
* URL to a page in Collectives on another instance: [Foreign Page](https://cloud.example.org/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123)
* URL to external website: [example.org](http://example.org/)
			`)
		})
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Link Testing/Link Source')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Link Target')
	})

	const clickLink = function(href, edit) {
		if (edit) {
			// Change to edit mode
			cy.switchPageMode(1)
			cy.getEditor()
				.should('be.visible')
				.find(`a[href="${href}"]`)
				.click()
		} else {
			cy.getReadOnlyEditor()
				.should('be.visible')
				.find(`a[href="${href}"]`)
				.click()
		}
	}

	// Expected to open file in viewer and stay on same page
	const testLinkToViewer = function(href, { fileName, viewerFileElement, edit = false }) {
		clickLink(href, edit)

		cy.location().should((loc) => {
			expect(loc.pathname).to.eq(sourceUrl.pathname)
			expect(loc.search).to.eq(sourceUrl.search)
		})
		cy.get('.modal-header').should('contain', fileName)
		cy.get(`.viewer__content > ${viewerFileElement}.viewer__file`).should('exist')

		cy.get('.modal-header > .icons-menu > button.header-close')
			.click()
	}

	// Expected to open in same tab
	const testLinkToSameTab = function(href, { edit = false, isPublic = false, expectedPathname = null, expectedSearch = null } = {}) {
		clickLink(href, edit)

		cy.url().then((newBaseUrl) => {
			const url = new URL(href, newBaseUrl)
			const encodedCollectiveName = encodeURIComponent('Link Testing')
			const pathname = isPublic
				? url.pathname.replace(`/${encodedCollectiveName}`, `/p/\\w+/${encodedCollectiveName}`)
				: url.pathname
			cy.location().should((loc) => {
				expect(loc.pathname).to.match(new RegExp(`^${expectedPathname || pathname}$`))
				expect(loc.search).to.eq(expectedSearch || url.search)
			})
		})

		cy.go('back')
	}

	// Expected to open in new tab
	const testLinkToNewTab = function(href, { edit = false, isPublic = false } = {}) {
		let openStub = null
		cy.window().then(win => {
			openStub = cy.stub(win, 'open').as('open')
		})
		clickLink(href, edit)

		cy.url().then((newBaseUrl) => {
			const url = new URL(href, newBaseUrl)
			// Text always calls full URLs
			const calledUrl = edit
				? url.href
				: href
			cy.get('@open')
				.should('be.calledWith', calledUrl)
				.then(() => {
					openStub.restore()
				})

			const encodedCollectiveName = encodeURIComponent('Link Testing')
			const pathname = isPublic
				? sourceUrl.pathname.replace(`/${encodedCollectiveName}`, `/p/\\w+/${encodedCollectiveName}`)
				: sourceUrl.pathname
			cy.location().should((loc) => {
				expect(loc.pathname).to.match(new RegExp(`^${pathname}$`))
				expect(loc.search).to.eq(sourceUrl.search)
			})
		})
	}

	describe('Link handling to viewer', function() {
		it('Opens link with absolute path to image in Nextcloud in viewer', function() {
			const href = `/index.php/apps/files/?dir=/&openfile=${imageId}#relPath=//test.png`
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img' })
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img', edit: true })
		})
		it('Opens link with absolute path to text file in Nextcloud in viewer', function() {
			const href = `/index.php/apps/files/?dir=/&openfile=${textId}#relPath=//test.md`
			testLinkToViewer(href, { fileName: 'test.md', viewerFileElement: '[data-text-el="editor-container"]' })
			testLinkToViewer(href, {
				fileName: 'test.md',
				viewerFileElement: '[data-text-el="editor-container"]',
				edit: true,
			})
		})
		it('Opens link with relative path to pdf in Nextcloud in viewer', function() {
			let href = `/index.php/apps/files/?dir=/&openfile=${pdfId}#relPath=test.pdf`
			testLinkToViewer(href, { fileName: 'test.pdf', viewerFileElement: 'iframe' })
			href = `/index.php/apps/files/?dir=/Collectives/Link Testing&openfile=${pdfId}#relPath=test.pdf`
			testLinkToViewer(href, { fileName: 'test.pdf', viewerFileElement: 'iframe', edit: true })
		})
	})

	describe('Link handling to collectives', function() {
		it('Opens link with URL to page in this collective in same/new tab depending on view/edit mode', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target`
			testLinkToSameTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with absolute path to page in this collective in same/new tab depending on view/edit mode', function() {
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			testLinkToSameTab(href)
			// Broken in Text on Nextcloud 25
			if (Cypress.env('ncVersion') !== 'stable25') {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with relative path to page in this collective with fileId in same/new tab depending on view/edit mode', function() {
			// Link without origin and containing `fileId` param gets rewritten by editor rendering
			// const href = `./Link%20Target?fileId=${linkTargetPageId}`
			const href = `/index.php/apps/files/?dir=/&openfile=${linkTargetPageId}#relPath=./Link%20Target`
			testLinkToSameTab(href, {
				expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
				expectedSearch: `?fileId=${linkTargetPageId}`,
			})
			// testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with relative path to page in this collective with fileId and outdated path in same/new tab depending on view/edit mode', function() {
			// Link without origin and containing `fileId` param gets rewritten by editor rendering
			// const href = `./Link%20Target%20Outdated?fileId=${linkTargetPageId}`
			const href = `/index.php/apps/files/?dir=/&openfile=${linkTargetPageId}#relPath=./Link%20Target%20Outdated`
			testLinkToSameTab(href, {
				expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
				expectedSearch: `?fileId=${linkTargetPageId}`,
			})
			// testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with relative path to page in this collective without fileId in same/new tab depending on view/edit mode', function() {
			const href = './Link%20Target'
			testLinkToSameTab(href)
			// Broken in Text on Nextcloud 25
			if (Cypress.env('ncVersion') !== 'stable25') {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with relative path to markdown file in this collective without fileId in same/new tab depending on view/edit mode', function() {
			// TODO: We want '.md' to be stripped when opening the link
			const href = './Link%20Target.md'
			testLinkToSameTab(href, { expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target' })
			// Special handling of links to markdown files is only in Collectives link handler
			// testLinkToNewTab(href, { edit: true, expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target' })
		})

		it('Opens link with URL to page in other collective with fileId in same/new tab depending on view/edit mode', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId}`
			testLinkToSameTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with absolute path to page in other collective without fileId in same/new tab depending on view/edit mode', function() {
			const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
			testLinkToSameTab(href)
			// Broken in Text on Nextcloud 25
			if (Cypress.env('ncVersion') !== 'stable25') {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with relative path from index page to page in this collective with fileId in same/new tab depending on view/edit mode', function() {
			cy.openPage('Parent')
			// Link without origin and containing `fileId` param gets rewritten by editor rendering
			// const href = `../Link%20Target.md?fileId=${linkTargetPageId}`
			const href = `/index.php/apps/files/?dir=&openfile=${linkTargetPageId}#relPath=../Link%20Target.md`
			testLinkToSameTab(href, {
				expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
				expectedSearch: `?fileId=${linkTargetPageId}`,
			})
			// testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with relative path from landing page to page in this collective with fileId in same/new tab depending on view/edit mode', function() {
			cy.openPage('Link Testing')
			// Link without origin and containing `fileId` param gets rewritten by editor rendering
			// const href = `./Link%20Target?fileId=${linkTargetPageId}`
			const href = `/index.php/apps/files/?dir=/&openfile=${linkTargetPageId}#relPath=./Link%20Target`
			testLinkToSameTab(href, {
				expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
				expectedSearch: `?fileId=${linkTargetPageId}`,
			})
			// testLinkToNewTab(href, { edit: true })
		})
	})

	describe('Link handling to Nextcloud', function() {
		it('Opens link with URL to another Nextcloud app in new tab', function() {
			const href = `${baseUrl}/index.php/apps/contacts`
			testLinkToNewTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with absolute path to another Nextcloud app in new tab', function() {
			const href = '/index.php/apps/contacts'
			testLinkToNewTab(href)
			// Broken in Text on Nextcloud 25
			if (Cypress.env('ncVersion') !== 'stable25') {
				testLinkToNewTab(href, { edit: true })
			}
		})
	})

	describe('Link handling to external', function() {
		it('Opens link to external website in new tab', function() {
			const href = 'http://example.org/'
			testLinkToNewTab(href)
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link to foreign Collectives page in new tab', function() {
			const href = 'https://cloud.example.org/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123'
			testLinkToNewTab(href)
			testLinkToNewTab(href, { edit: true })
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
			cy.openCollectiveMenu('Link Testing')
			cy.intercept('POST', '**/_api/*/share').as('createShare')
			cy.clickMenuButton('Share link')
			cy.wait('@createShare')
			cy.intercept('PUT', '**/_api/*/share/*').as('updateShare')
			cy.get('input#shareEditable')
				.check({ force: true })
			cy.wait('@updateShare')
			cy.get('input#shareEditable')
				.should('be.checked')
			cy.get('button')
				.contains('Copy share link')
				.click()
			cy.get('@clipBoardWriteText').should('have.been.calledOnce')
		})
		it('Public share: opens link with absolute path to page in this collective in same/new tab depending on view/edit mode', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			// Broken in Text on Nextcloud 25
			if (Cypress.env('ncVersion') !== 'stable25') {
				testLinkToSameTab(href, { isPublic: true })
				testLinkToNewTab(href, { edit: true, isPublic: true })
			}
		})
		it('Public share: opens link to external website in new tab', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = 'http://example.org/'
			testLinkToNewTab(href, { isPublic: true })
			testLinkToNewTab(href, { edit: true, isPublic: true })
		})
	})
})
