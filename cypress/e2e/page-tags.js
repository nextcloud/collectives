/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

describe('Page tags', function() {
	let collectiveId, pageTaggedId, pageTaggedSlugUrl
	const collectiveName = 'Page Tags'
	const collectiveSlug = 'Page-Tags'
	const pageTaggedName = 'Tagged Page'
	const pageTaggedSlug = 'Tagged-Page'
	const pageUntaggedName = 'Untagged Page'

	before(function() {
		cy.loginAs('bob')
		cy.deleteAndSeedCollective(collectiveName)
			.seedPage(pageUntaggedName, '', 'Readme.md')
			.seedPage(pageTaggedName, '', 'Readme.md').then(({ collectiveId: cid, pageId: pid }) => {
				collectiveId = cid
				pageTaggedId = pid
				pageTaggedSlugUrl = `/apps/collectives/${collectiveSlug}-${collectiveId}/${pageTaggedSlug}-${pageTaggedId}`
			})
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit(pageTaggedSlugUrl)
		// make sure the page list loaded properly
		cy.contains('.app-content-list-item a', pageTaggedName)
	})

	describe('Manage tags for page', function() {
		it('Allows to manage tags', function() {
			cy.openPageTitleMenu()
			cy.clickMenuButton('Manage tags')

			// Create and add tag
			cy.get('.tags-modal__input input[type="text"]')
				.type('initial')
			cy.get('.tags-modal__tag-create')
				.click()
			cy.get('.toast-success').should('contain', 'Created tag initial')
			cy.get('.toast-success').should('contain', 'Added tag initial to page')

			// Choose tag color
			cy.contains('.tags-modal__tag', 'initial')
				.find('.tags-modal__tag-color')
				.click()
			cy.get('.color-picker__simple-color-circle')
				.first()
				.click()
			cy.contains('.button-vue', 'Choose')
				.click()
			cy.get('.toast-success').should('contain', 'Updated tag initial')
			cy.contains('.page-tags-container .tag', 'initial')

			// Rename tag
			cy.contains('.tags-modal__tag', 'initial')
				.find('.action-item')
				.click()
			cy.clickMenuButton('Rename')
			cy.get('.tags-modal__name-form input[type="text"]')
				.type('{selectAll}testing{enter}')
			cy.get('.toast-success').should('contain', 'Updated tag testing')
			cy.contains('.tags-modal__tag', 'testing')
				.should('be.visible')
			cy.contains('.tags-modal__tag', 'initial')
				.should('not.exist')
			cy.contains('.page-tags-container .tag', 'testing')

			// Mark tag as deleted
			cy.contains('.tags-modal__tag', 'testing')
				.find('.action-item')
				.click()
			cy.clickMenuButton('Delete')

			cy.contains('.modal-container', 'Manage tags')
				.find('.modal-container__close')
				.click()
			cy.get('.toast-success').should('contain', 'Deleted 1 tag')
			cy.contains('.page-tags-container .tag', 'testing')
				.should('not.exist')
		})

		it('Allows to view extra tags in page', function() {
			cy.openPageTitleMenu()
			cy.clickMenuButton('Manage tags')

			// Create and add several tags
			const tags = Array.from({ length: 7 }, (_, i) => `tag${i + 1}`)
			for (const tag of tags) {
				cy.get('.tags-modal__input input[type="text"]')
					.should('be.visible')
					.clear()
				cy.wait(200) // eslint-disable-line cypress/no-unnecessary-waiting
				cy.get('.tags-modal__input input[type="text"]')
					.type(tag)
				cy.get('.tags-modal__tag-create')
					.click()
				cy.get('.toast-success').should('contain', `Created tag ${tag}`)
			}
			cy.contains('.modal-container', 'Manage tags')
				.find('.modal-container__close')
				.click()

			for (const tag of tags.slice(0, 5)) {
				cy.contains('.page-tags-container .tag', tag)
			}

			cy.get('.page-tags-container .tag.tag-invisible')
				.click()
			for (const tag of tags.slice(5, 6)) {
				cy.contains('.page-tags-invisible-popover .tag', tag)
			}
		})

		it('Allows to filter page list by clicking on tag in titlebar', function() {
			cy.get('.page-tags-container .tag.tag-invisible')
				.click()
			cy.contains('.page-tags-invisible-popover .tag', 'tag6')
				.click()
			cy.get('.page-filter-tags')
				.contains('tag6')

			cy.contains('.app-content-list-item a', pageTaggedName)
				.should('be.visible')
			cy.contains('.app-content-list-item a', pageUntaggedName)
				.should('not.exist')

			cy.contains('.page-filter-tags li', 'tag6')
				.find('.remove-button')
				.click()

			cy.contains('.page-filter-tags li', 'tag6')
				.should('not.exist')
			cy.contains('.app-content-list-item a', pageUntaggedName)
				.should('be.visible')
		})

		it('Allows to filter page list via dropdown in page filter', function() {
			// Search for tag in page filter, open dropdown and close it on <Esc>
			cy.get('input[name="pageFilter"]')
				.type('tag')
			cy.get('.page-filter-tag-select')
				.contains('tag1')
			cy.get('.page-filter-tag-select')
				.contains('tag2')
			cy.get('input[name="pageFilter"]')
				.type('{esc}')
			cy.get('.page-filter-tag-select')
				.should('not.exist')

			// Select a tag from dropdown
			cy.get('input[name="pageFilter"]')
				.type('{selectAll}tag')
			cy.get('.page-filter-tag-select')
				.contains('tag2')
				.click()

			cy.get('.page-filter-tags')
				.contains('tag2')

			cy.contains('.app-content-list-item a', pageTaggedName)
				.should('be.visible')
			cy.contains('.app-content-list-item a', pageUntaggedName)
				.should('not.be.visible')

			// Select a second tag from dropdown
			cy.get('input[name="pageFilter"]')
				.type('{selectAll}tag')
			cy.contains('.page-filter-tag-select li', 'tag2')
				.should('not.exist')
			cy.get('.page-filter-tag-select')
				.contains('tag3')
				.click()

			cy.get('.page-filter-tags')
				.contains('tag2')
			cy.get('.page-filter-tags')
				.contains('tag3')

			cy.contains('.app-content-list-item a', pageTaggedName)
				.should('be.visible')
			cy.contains('.app-content-list-item a', pageUntaggedName)
				.should('not.be.visible')
		})
	})
})
