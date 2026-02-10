/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page slug URLs', function() {
	let pageId, slugUrl
	const collectiveName = 'Slug Links äöü'
	const collectiveSlug = 'Slug-Links-aou'
	const pageName = 'Page üöä'
	const pageSlug = 'Page-uoa'
	const nameUrl = `/apps/collectives/${encodeURIComponent(collectiveName)}/${encodeURIComponent(pageName)}`

	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective(collectiveName)
			.seedPage(pageName, '', 'Readme.md').then(({ collectiveId, pageId: pid }) => {
				pageId = pid
				slugUrl = `/apps/collectives/${collectiveSlug}-${collectiveId}/${pageSlug}-${pageId}`
			})
	})

	beforeEach(function() {
		cy.loginAs('bob')
	})

	it('Opens URL with collective and page slugs', function() {
		cy.visit(slugUrl)
		cy.location().should((loc) => {
			expect(loc.pathname).to.eq(`/index.php${slugUrl}`)
		})
	})

	it('Opens URL with collective and page name without pageId', function() {
		cy.visit(nameUrl)
		cy.location().should((loc) => {
			expect(loc.pathname).to.eq(`/index.php${slugUrl}`)
		})
	})

	it('Opens URL with collective and page name with pageId', function() {
		cy.visit(`${nameUrl}?fileId=${pageId}`)
		cy.location().should((loc) => {
			expect(loc.pathname).to.eq(`/index.php${slugUrl}`)
		})
	})
})
