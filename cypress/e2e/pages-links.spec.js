/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const baseUrl = Cypress.env('baseUrl')
let imageId, pdfId, textId, sourceUrl
let anotherCollectiveFirstPageId, anotherCollectiveId, linkTestingCollectiveId, linkTargetPageId

describe('Page link handling', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Another Collective')
			.seedPage('First Page', '', 'Readme.md').then(({ collectiveId, pageId }) => {
				anotherCollectiveId = collectiveId
				anotherCollectiveFirstPageId = pageId
			})
		cy.deleteAndSeedCollective('Link Testing')
			.seedPage('Parent', '', 'Readme.md')
			.seedPage('Child', '', 'Parent.md')
			.seedPage('Link Target', '', 'Readme.md').then(({ pageId }) => {
				linkTargetPageId = pageId
			})
			.seedPage('Link Source', '', 'Readme.md').then(({ collectiveId, pageId }) => {
				linkTestingCollectiveId = collectiveId
				sourceUrl = new URL(`${baseUrl}/index.php/apps/collectives/Link-Testing-${collectiveId}/Link-Source-${pageId}`)
			})
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

* Slugified URL to page in this collective: [Link Target](${baseUrl}/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId})
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
* URL to a page in Collectives on another instance: [Foreign Page](https://github.com/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123)
* URL to external website: [github.com](https://github.com/)
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

		cy.get('.link-view-bubble .widgets--list')
			.find('a.widget-file, a.collective-page, a.widget-default')
			.click()
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
			const encodedSlugCollectiveUrl = `Link-Testing-${linkTestingCollectiveId}`
			const pathname = isPublic
				? url.pathname
						.replace(`/${encodedCollectiveName}`, `/p/\\w+/${encodedCollectiveName}`)
						.replace(`/${encodedSlugCollectiveUrl}`, `/p/\\w+/${encodedSlugCollectiveUrl}`)
				: url.pathname
			cy.location().should((loc) => {
				expect(loc.pathname).to.match(new RegExp(`^${expectedPathname || pathname}$`))
				expect(loc.search).to.eq(expectedSearch ?? url.search)
			})
		})

		cy.go('back')
	}

	// Expected to open in new tab
	const testLinkToNewTab = function(href, { edit = false, isPublic = false } = {}) {
		clickLink(href, edit)

		cy.url().then(() => {
			const encodedCollectiveName = encodeURIComponent('Link Testing')
			const encodedSlugCollectiveUrl = `Link-Testing-${linkTestingCollectiveId}`
			const pathname = isPublic
				? sourceUrl.pathname
						.replace(`/${encodedCollectiveName}`, `/p/\\w+/${encodedCollectiveName}`)
						.replace(`/${encodedSlugCollectiveUrl}`, `/p/\\w+/${encodedSlugCollectiveUrl}`)
				: sourceUrl.pathname
			cy.location().should((loc) => {
				expect(loc.pathname).to.match(new RegExp(`^${pathname}$`))
				expect(loc.search).to.eq(sourceUrl.search)
			})
		})
	}

	describe('Link handling to viewer in view mode', function() {
		it('Opens link with absolute path to image in Nextcloud in viewer', function() {
			const href = `/index.php/f/${imageId}`
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img' })
		})
		it('Opens link with absolute path to text file in Nextcloud in viewer', function() {
			const href = `/index.php/f/${textId}`
			testLinkToViewer(href, { fileName: 'test.md', viewerFileElement: '[data-text-el="editor-container"]' })
		})
		it('Opens link with relative path to pdf in Nextcloud in viewer', function() {
			const href = `/index.php/f/${pdfId}`
			testLinkToViewer(href, { fileName: 'test.pdf', viewerFileElement: 'iframe' })
		})
	})

	describe('Link handling to viewer in edit mode', function() {
		it('Opens link with absolute path to image in Nextcloud in viewer', function() {
			const href = `/index.php/f/${imageId}`
			cy.switchToEditMode()
			testLinkToViewer(href, { fileName: 'test.png', viewerFileElement: 'img', edit: true })
		})
		it('Opens link with absolute path to text file in Nextcloud in viewer', function() {
			const href = `/index.php/f/${textId}`
			cy.switchToEditMode()
			testLinkToViewer(href, {
				fileName: 'test.md',
				viewerFileElement: '[data-text-el="editor-container"]',
				edit: true,
			})
		})
		it('Opens link with relative path to pdf in Nextcloud in viewer', function() {
			const href = `/index.php/f/${pdfId}`
			cy.switchToEditMode()
			testLinkToViewer(href, { fileName: 'test.pdf', viewerFileElement: 'iframe', edit: true })
		})
	})

	describe('Link handling to collectives in view mode', function() {
		it('Opens link with slugified URL to page in this collective in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with URL to page in this collective in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target`
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with absolute path to page in this collective in same tab', function() {
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with relative path to page in this collective with fileId in same tab', function() {
			const href = `./Link%20Target?fileId=${linkTargetPageId}`
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
				expectedSearch: '',
			})
		})
		it('Opens link with relative path to page in this collective with fileId and outdated path in same tab', function() {
			const href = `./Link%20Target%20Outdated?fileId=${linkTargetPageId}`
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
				expectedSearch: '',
			})
		})
		it('Opens link with relative path to page in this collective without fileId in same tab', function() {
			const href = './Link%20Target'
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with relative path to markdown file in this collective without fileId in same tab', function() {
			const href = './Link%20Target.md'
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})

		it('Opens link with URL to page in other collective with fileId in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId}`
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Another-Collective-${anotherCollectiveId}/First-Page-${anotherCollectiveFirstPageId}`,
				expectedSearch: '',
			})
		})
		it('Opens link with absolute path to page in other collective without fileId in same tab', function() {
			const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
			testLinkToSameTab(href, {
				expectedPathname: `/index.php/apps/collectives/Another-Collective-${anotherCollectiveId}/First-Page-${anotherCollectiveFirstPageId}`,
			})
		})
	})

	describe('Link handling to collectives in edit mode', function() {
		it('Opens link with slugified URL to page in this collective in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with URL to page in this collective in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Link%20Testing/Link%20Target`
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with absolute path to page in this collective in same tab', function() {
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with relative path to page in this collective with fileId in same tab', function() {
			const href = `./Link%20Target?fileId=${linkTargetPageId}`
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
				expectedSearch: '',
			})
		})
		it('Opens link with relative path to page in this collective with fileId and outdated path in same tab', function() {
			const href = `./Link%20Target%20Outdated?fileId=${linkTargetPageId}`
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
				expectedSearch: '',
			})
		})
		it('Opens link with relative path to page in this collective without fileId in same tab', function() {
			const href = './Link%20Target'
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Opens link with relative path to markdown file in this collective without fileId in same tab', function() {
			const href = './Link%20Target.md'
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})

		it('Opens link with URL to page in other collective with fileId in same tab', function() {
			const href = `${baseUrl}/index.php/apps/collectives/Another%20Collective/First%20Page?fileId=${anotherCollectiveFirstPageId}`
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Another-Collective-${anotherCollectiveId}/First-Page-${anotherCollectiveFirstPageId}`,
				expectedSearch: '',
			})
		})
		it('Opens link with absolute path to page in other collective without fileId in same tab', function() {
			const href = '/index.php/apps/collectives/Another%20Collective/First%20Page'
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				expectedPathname: `/index.php/apps/collectives/Another-Collective-${anotherCollectiveId}/First-Page-${anotherCollectiveFirstPageId}`,
				expectedSearch: '',
			})
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
			const href = 'https://github.com/'
			testLinkToNewTab(href)
		})
		it('Opens link to foreign Collectives page in new tab', function() {
			const href = 'https://github.com/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123'
			testLinkToNewTab(href)
		})
	})

	describe('Link handling to external in edit mode', function() {
		it('Opens link to external website in new tab', function() {
			const href = 'https://github.com/'
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true })
		})
		it('Opens link to foreign Collectives page in new tab', function() {
			const href = 'https://github.com/apps/collectives/Foreign%20Collective/Foreign%20Page?fileId=123'
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true })
		})
	})

	describe('Link handling in public share', function() {
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
			cy.intercept('POST', '**/api/v1.0/collectives/*/shares').as('createShare')
			cy.get('.sharing-entry button.new-share-link')
				.click()
			cy.wait('@createShare')
			cy.get('.sharing-entry .share-select')
				.click()
			cy.intercept('PUT', '**/api/v1.0/collectives/*/shares/*').as('updateShare')
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
			testLinkToSameTab(href, {
				isPublic: true,
				expectedPathname: `/index.php/apps/collectives/p/\\w+/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Public share in edit mode: opens link with absolute path to page in this collective in same tab', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = '/index.php/apps/collectives/Link%20Testing/Link%20Target'
			cy.switchToEditMode()
			testLinkToSameTab(href, {
				edit: true,
				isPublic: true,
				expectedPathname: `/index.php/apps/collectives/p/\\w+/Link-Testing-${linkTestingCollectiveId}/Link-Target-${linkTargetPageId}`,
			})
		})
		it('Public share in view mode: opens link to external website in new tab', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = 'https://github.com/'
			testLinkToNewTab(href, { isPublic: true })
		})
		it('Public share in edit mode: opens link to external website in new tab', function() {
			cy.logout()
			cy.visit(`${shareUrl}/Link Source`)
			const href = 'https://github.com/'
			cy.switchToEditMode()
			testLinkToNewTab(href, { edit: true, isPublic: true })
		})
	})
})

describe('Page link preview handling', function() {
	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Link Preview Testing')
			.seedPage('Link Source', '', 'Readme.md')
			.seedPage('Link Target', '', 'Readme.md')
			.then(({ pageId }) => {
				const pageUrls = [
					`${baseUrl}/index.php/apps/collectives/Link%20Preview%20Testing/Link%20Target?fileId=${pageId}`,
					`${baseUrl}/index.php/apps/collectives/Link%20Preview%20Testing/Link%20Target`,
					`${baseUrl}/index.php/apps/collectives/p/qqqYoCgYRnZ598p/Link%20Preview%20Testing/Link%20Target?fileId=${pageId}`,
					`${baseUrl}/index.php/apps/collectives/p/qqqYoCgYRnZ598p/Link%20Preview%20Testing/Link%20Target`,
				]
				cy.seedPageContent('Link%20Preview%20Testing/Link%20Target.md', 'Some content')
					.seedPageContent('Link%20Preview%20Testing/Link%20Source.md', `
## Link previews to own Collective

[Internal link to page with fileId](${pageUrls[0]} (preview))

[Internal link to page without fileId](${pageUrls[1]} (preview))

[Public link to page with fileId](${pageUrls[2]} (preview))

[Public link to page without fileId](${pageUrls[3]} (preview))
						`)
			})
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Link Preview Testing/Link Source')
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', 'Link Target')
	})

	it('Shows previews in view and edit mode', function() {
		cy.getEditorContent()
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)

		cy.switchToEditMode()
		cy.getEditorContent(true)
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)
	})

	let shareUrl

	it('Share the collective', function() {
		cy.visit('/apps/collectives', {
			onBeforeLoad(win) {
				// navigator.clipboard doesn't exist on HTTP requests (in CI), so let's create it
				if (!win.navigator.clipboard) {
					win.navigator.clipboard = {
						__proto__: {
							writeText: () => {
							},
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
		cy.openCollectiveMenu('Link Preview Testing')
		cy.clickMenuButton('Share link')
		cy.intercept('POST', '**/api/v1.0/collectives/*/shares').as('createShare')
		cy.get('.sharing-entry button.new-share-link')
			.click()
		cy.wait('@createShare')
		cy.get('.sharing-entry .share-select')
			.click()
		cy.intercept('PUT', '**/api/v1.0/collectives/*/shares/*').as('updateShare')
		cy.get('.sharing-entry .share-select .dropdown-item')
			.contains('Can edit')
			.click()
		cy.wait('@updateShare')
		cy.get('button.sharing-entry__copy')
			.click()
		cy.get('@clipBoardWriteText').should('have.been.calledOnce')
	})

	it('Public share: Shows previews in view and edit mode', function() {
		cy.logout()
		cy.visit(`${shareUrl}/Link Source`)

		cy.getEditorContent()
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)

		cy.switchToEditMode()
		cy.getEditorContent(true)
			.find('.widget-custom a.collective-page')
			.should('have.length', 4)
	})
})
