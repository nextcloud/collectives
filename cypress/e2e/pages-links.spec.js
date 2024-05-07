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

const baseUrl = Cypress.env('baseUrl')
const sourceUrl = new URL(`${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Source`)
let imageId, pdfId, textId
let anotherCollectiveFirstPageId, linkTargetPageId

describe('Page link handling', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Another Collective')
			.seedPage('First Page', '', 'Readme.md').then(({ pageId }) => {
				anotherCollectiveFirstPageId = pageId
			})
		cy.deleteAndSeedCollective('Link Testing')
			.seedPage('Parent', '', 'Readme.md')
			.seedPage('Child', '', 'Parent.md')
			.seedPage('Link Target', '', 'Readme.md').then(({ pageId }) => {
				linkTargetPageId = pageId
			})
			.seedPage('Link Source', '', 'Readme.md')
		cy.seedPageContent('Link%20Testing/Link%20Target.md', 'Some content')
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

* Relative path to page in this collective with fileId: [Link Target](./Link%20Target?fileId=${linkTargetPageId})
			`)
			cy.seedPageContent('Link%20Testing/Parent/Readme.md', `
## Links supposed to open in same window

* Relative path to page in this collective with fileId: [../Link Target.md](../Link%20Target.md?fileId=${linkTargetPageId})
			`)
			cy.seedPageContent('Link%20Testing/Link%20Source.md', `
## Links supposed to open in viewer

* Absolute path to image in Nextcloud: [image](/index.php/f/${imageId})
* Absolute path to text file in Nextcloud: [test.md](/index.php/f/${textId})
* Relative path to pdf file in Nextcloud: [test.pdf](/index.php/f/${pdfId})
* Absolute path to image in Nextcloud (pre NC29): [image](//test.png?fileId=${imageId})
* Absolute path to text file in Nextcloud (pre NC29): [test.md](//test.md?fileId=${textId})
* Relative path to pdf file in Nextcloud (pre NC29): [test.pdf](test.pdf?fileId=${pdfId})

## Links supposed to open in same window

* URL to page in this collective: [Link Target](${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target)
* Absolute path to page in this collective: [Link Target](/index.php/apps/collectives/Link%20Testing/Link%20Target)
* Relative path to page in this collective with fileId: [Link Target](./Link%20Target?fileId=${linkTargetPageId})
* Relative path to page in this collective with fileId and outdated path: [Link Target](./Link%20Target%20Outdated?fileId=${linkTargetPageId})
* Relative path to page in this collective without fileId: [Link Target](./Link%20Target)
* Relative path to markdown file in this collective: [Link Target](./Link%20Target.md)

* URL to page in other collective with fileId: [Another Collective/First Page](${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId})
* Absolute path to page in other collective without fileId: [Another Collective/First Page](/index.php/apps/collectives/Another%20Collective/First%20Page)

## Links supposed to open in new window

* URL to another app in Nextcloud: [Contacts](${baseUrl}/index.php/apps/contacts)
* Absolute path to another app in Nextcloud: [Contacts](/index.php/apps/contacts)
* URL to a page in Collectives on another instance: [Foreign Page](https://example.org/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123)
* URL to external website: [example.org](http://example.org/)
* Some content
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
		// Editor loaded will reset page content, which interferes with clicked link. So let's wait for it.
		cy.getEditor()

		cy.getEditorContent(edit)
			.find(`a[href="${href}"]`)
			.as('link')
			.scrollIntoView()
		cy.get('@link')
			.click()

		// Starting with Nextcloud 29, clicking on a link opens the link bubble
		if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
			cy.get('.link-view-bubble .widgets--list')
				.find('a.widget-file, a.collective-page, a.widget-default')
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
		cy.get(`.viewer__content ${viewerFileElement}.viewer__file, .viewer__content .viewer__file ${viewerFileElement}`).should('exist')

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
			if (['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				cy.get('@open')
					.should('be.calledWith', calledUrl)
					.then(() => {
						openStub.restore()
					})
			}

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

	describe('Link handling to viewer in view mode', function() {
		it('Opens link with absolute path to image in Nextcloud in viewer', function() {
			let href = null
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				href = `/index.php/f/${imageId}`
			} else {
				href = `/index.php/apps/files/?dir=/&openfile=${imageId}#relPath=//test.png`
			}
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img' })
		})
		it('Opens link with absolute path to text file in Nextcloud in viewer', function() {
			let href = null
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				href = `/index.php/f/${textId}`
			} else {
				href = `/index.php/apps/files/?dir=/&openfile=${textId}#relPath=//test.md`
			}
			testLinkToViewer(href, { fileName: 'test.md', viewerFileElement: '[data-text-el="editor-container"]' })
		})
		it('Opens link with relative path to pdf in Nextcloud in viewer', function() {
			let href = null
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				href = `/index.php/f/${pdfId}`
			} else {
				href = `/index.php/apps/files/?dir=/&openfile=${pdfId}#relPath=test.pdf`
			}
			testLinkToViewer(href, { fileName: 'test.pdf', viewerFileElement: 'iframe' })
		})
	})

	describe('Link handling to viewer in edit mode', function() {
		it('Opens link with absolute path to image in Nextcloud in viewer', function() {
			let href = null
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				href = `/index.php/f/${imageId}`
			} else {
				href = `/index.php/apps/files/?dir=/&openfile=${imageId}#relPath=//test.png`
			}
			cy.switchToEditMode()
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img', edit: true })
		})
		it('Opens link with absolute path to text file in Nextcloud in viewer', function() {
			let href = null
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				href = `/index.php/f/${textId}`
			} else {
				href = `/index.php/apps/files/?dir=/&openfile=${textId}#relPath=//test.md`
			}
			cy.switchToEditMode()
			testLinkToViewer(href, {
				fileName: 'test.md',
				viewerFileElement: '[data-text-el="editor-container"]',
				edit: true,
			})
		})
		it('Opens link with relative path to pdf in Nextcloud in viewer', function() {
			let href = null
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				href = `/index.php/f/${pdfId}`
			} else {
				href = `/index.php/apps/files/?dir=/Collectives/Link Testing&openfile=${pdfId}#relPath=test.pdf`
			}
			cy.switchToEditMode()
			testLinkToViewer(href, { fileName: 'test.pdf', viewerFileElement: 'iframe', edit: true })
		})
	})

	describe('Link handling to collectives in view mode', function() {
		it('Opens link with URL to page in this collective in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target`
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToSameTab(href)
			}
		})
		it('Opens link with absolute path to page in this collective in same tab', function() {
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToSameTab(href)
			}
		})
		it('Opens link with relative path to page in this collective with fileId in same tab', function() {
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				const href = `./Link%20Target?fileId=${linkTargetPageId}`
				testLinkToSameTab(href)
			} else {
				// Link without origin and containing `fileId` param gets rewritten by editor rendering
				// const href = `./Link%20Target?fileId=${linkTargetPageId}`
				const href = `/index.php/apps/files/?dir=/&openfile=${linkTargetPageId}#relPath=./Link%20Target`
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			}
		})
		it('Opens link with relative path to page in this collective with fileId and outdated path in same tab', function() {
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				const href = `./Link%20Target%20Outdated?fileId=${linkTargetPageId}`
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				// Link without origin and containing `fileId` param gets rewritten by editor rendering
				// const href = `./Link%20Target%20Outdated?fileId=${linkTargetPageId}`
				const href = `/index.php/apps/files/?dir=/&openfile=${linkTargetPageId}#relPath=./Link%20Target%20Outdated`
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			}
		})
		it('Opens link with relative path to page in this collective without fileId in same tab', function() {
			const href = './Link%20Target'
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToSameTab(href)
			}
		})
		it('Opens link with relative path to markdown file in this collective without fileId in same tab', function() {
			// TODO: We want '.md' to be stripped when opening the link
			const href = './Link%20Target.md'
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToSameTab(href, { expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target' })
			}
		})

		it('Opens link with URL to page in other collective with fileId in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId}`
			testLinkToSameTab(href)
		})
		it('Opens link with absolute path to page in other collective without fileId in same tab', function() {
			const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					expectedSearch: `?fileId=${anotherCollectiveFirstPageId}`,
				})
			} else {
				testLinkToSameTab(href)
			}
		})
		it('Opens link with relative path from index page to page in this collective with fileId in same tab', function() {
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				cy.openPage('Parent')
				// Link without origin and containing `fileId` param gets rewritten by editor rendering
				// const href = `../Link%20Target.md?fileId=${linkTargetPageId}`
				const href = `/index.php/apps/files/?dir=&openfile=${linkTargetPageId}#relPath=../Link%20Target.md`
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			}
		})
		it('Opens link with relative path from landing page to page in this collective with fileId in same tab', function() {
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				cy.openPage('Link Testing')
				// Link without origin and containing `fileId` param gets rewritten by editor rendering
				// const href = `./Link%20Target?fileId=${linkTargetPageId}`
				const href = `/index.php/apps/files/?dir=/&openfile=${linkTargetPageId}#relPath=./Link%20Target`
				testLinkToSameTab(href, {
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			}
		})
	})

	describe('Link handling to collectives in edit mode', function() {
		it('Opens link with URL to page in this collective in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target`
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					edit: true,
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with absolute path to page in this collective in same tab', function() {
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					edit: true,
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with relative path to page in this collective with fileId in same tab', function() {
			const href = `./Link%20Target?fileId=${linkTargetPageId}`
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, { edit: true })
			} else {
				// Link without origin and containing `fileId` param gets rewritten by editor rendering
			}
		})
		it('Opens link with relative path to page in this collective with fileId and outdated path in same tab', function() {
			const href = `./Link%20Target%20Outdated?fileId=${linkTargetPageId}`
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					edit: true,
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				// Link without origin and containing `fileId` param gets rewritten by editor rendering
			}
		})
		it('Opens link with relative path to page in this collective without fileId in same tab', function() {
			const href = './Link%20Target'
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					edit: true,
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with relative path to markdown file in this collective without fileId in same tab', function() {
			// TODO: We want '.md' to be stripped when opening the link
			const href = './Link%20Target.md'
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					edit: true,
					expectedPathname: '/index.php/apps/collectives/Link%20Testing/Link%20Target',
					expectedSearch: `?fileId=${linkTargetPageId}`,
				})
			} else {
				// Special handling of links to markdown files is only in Collectives link handler
			}
		})

		it('Opens link with URL to page in other collective with fileId in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId}`
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, { edit: true })
			} else {
				testLinkToNewTab(href, { edit: true })
			}
		})
		it('Opens link with absolute path to page in other collective without fileId in same tab', function() {
			const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
			cy.switchToEditMode()
			// Starting with Nextcloud 29, internal links will always open in same tab (also in edit mode)
			if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
				testLinkToSameTab(href, {
					edit: true,
					expectedSearch: `?fileId=${anotherCollectiveFirstPageId}`,
				})
			} else {
				testLinkToNewTab(href, { edit: true })
			}
		})
	})

	describe('Link handling to Nextcloud in view mode', function() {
		it('Opens link with URL to another Nextcloud app in new tab', function() {
			const href = `${baseUrl}/index.php/apps/contacts`
			testLinkToNewTab(href)
		})
		it('Opens link with absolute path to another Nextcloud app in new tab', function() {
			const href = '/index.php/apps/contacts'
			testLinkToNewTab(href)
		})
	})

	describe('Link handling to Nextcloud in edit mode', function() {
		it('Opens link with URL to another Nextcloud app in new tab', function() {
			const href = `${baseUrl}/index.php/apps/contacts`
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link with absolute path to another Nextcloud app in new tab', function() {
			const href = '/index.php/apps/contacts'
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true })
		})
	})

	describe('Link handling to external in view mode', function() {
		it('Opens link to external website in new tab', function() {
			const href = 'http://example.org/'
			testLinkToNewTab(href)
		})
		it('Opens link to foreign Collectives page in new tab', function() {
			const href = 'https://example.org/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123'
			testLinkToNewTab(href)
		})
	})

	describe('Link handling to external in edit mode', function() {
		it('Opens link to external website in new tab', function() {
			const href = 'http://example.org/'
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link to foreign Collectives page in new tab', function() {
			const href = 'https://example.org/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123'
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true })
		})
	})

	describe('Link handling public share', function() {
		if (['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
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
				cy.clickMenuButton('Share link')
				cy.intercept('POST', '**/_api/*/share').as('createShare')
				cy.get('.sharing-entry button.new-share-link')
					.click()
				cy.wait('@createShare')
				cy.get('.sharing-entry .share-select')
					.click()
				cy.intercept('PUT', '**/_api/*/share/*').as('updateShare')
				cy.get('.sharing-entry .share-select .dropdown-item')
					.contains('Can edit')
					.click()
				cy.wait('@updateShare')
				cy.get('button.sharing-entry__copy')
					.click()
				cy.get('@clipBoardWriteText').should('have.been.calledOnce')
			})
			it('Public share in view mode: opens link with absolute path to page in this collective in same tab', function() {
				cy.logout()
				cy.visit(`${shareUrl}/Link Source`)
				const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
				testLinkToSameTab(href, { isPublic: true })
			})
			it('Public share in edit mode: opens link with absolute path to page in this collective in same tab', function() {
				cy.logout()
				cy.visit(`${shareUrl}/Link Source`)
				const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
				cy.switchToEditMode()
				if (!['stable26', 'stable27', 'stable28'].includes(Cypress.env('ncVersion'))) {
					testLinkToSameTab(href, { edit: true, isPublic: true })
				} else {
					testLinkToNewTab(href, { edit: true, isPublic: true })
				}
			})
			it('Public share in view mode: opens link to external website in new tab', function() {
				cy.logout()
				cy.visit(`${shareUrl}/Link Source`)
				const href = 'http://example.org/'
				testLinkToNewTab(href, { isPublic: true })
			})
			it('Public share in edit mode: opens link to external website in new tab', function() {
				cy.logout()
				cy.visit(`${shareUrl}/Link Source`)
				const href = 'http://example.org/'
				cy.switchToEditMode()
				testLinkToNewTab(href, { edit: true, isPublic: true })
			})
		}
	})
})
