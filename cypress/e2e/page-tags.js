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

			// Delete tag
			cy.contains('.tags-modal__tag', 'testing')
				.find('.action-item')
				.click()
			cy.clickMenuButton('Delete')
			cy.contains('.tags-modal__delete-dialog .button-vue', 'Delete')
				.click()
			cy.get('.toast-success').should('contain', 'Deleted tag testing')
			cy.contains('.tags-modal__tag', 'testing')
				.should('not.exist')
		})

		it('Allows to view extra tags in page', function() {
			cy.openPageTitleMenu()
			cy.clickMenuButton('Manage tags')

			// Create and add several tags
			const tags = Array.from({ length: 7 }, (_, i) => `tag${i + 1}`)
			for (const tag of tags) {
				cy.get('.tags-modal__input input[type="text"]')
					.type(tag)
				cy.get('.tags-modal__tag-create')
					.click()
				cy.get('.toast-success').should('contain', `Created tag ${tag}`)
				cy.wait(300) // eslint-disable-line cypress/no-unnecessary-waiting
			}
			cy.contains('.modal-container', 'Manage tags')
				.find('.modal-container__close')
				.click()

			for (const tag of tags.slice(0, 5)) {
				cy.contains('.page-tags-container .tag', tag)
			}

			cy.reload()
			cy.get('.page-tags-container .tag.tag-invisible')
				.click()
			for (const tag of tags.slice(5, 6)) {
				cy.contains('.page-tags-invisible-popover .tag', tag)
			}
		})

		it('Allows to filter pages by tags', function() {
			// TODO
		})
	})
})
