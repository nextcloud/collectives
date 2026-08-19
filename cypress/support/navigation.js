/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

Cypress.Commands.add('openApp', (appName) => {
	Cypress.log()
	cy.get(`nav.app-menu li[data-app-id="${appName}"] a`).click()
})

Cypress.Commands.add('openPage', (pageName) => {
	Cypress.log()
	cy.contains('.page-list-item .app-navigation-entry-link', pageName).click()
})

Cypress.Commands.add('getPageListItem', (title) => {
	Cypress.log()
	return cy.contains('.page-list-drag-item .app-navigation-entry__name', new RegExp(`^${title}$`))
		.closest('.page-list-drag-item')
})

Cypress.Commands.add('openPageMenu', (pageName) => {
	Cypress.log()
	cy.contains('.page-list-item', pageName)
		.find('> .app-navigation-entry .action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('openPageTitleMenu', () => {
	Cypress.log()
	cy.get('.titlebar-buttons .action-item')
		.click()
})

Cypress.Commands.add('openCollective', (collectiveName) => {
	Cypress.log()
	cy.routeTo(collectiveName)
})

Cypress.Commands.add('openCollectiveSelector', () => {
	Cypress.log()
	cy.get('body').then(($body) => {
		if ($body.find('.collective-selector-popover-content:visible').length === 0) {
			cy.get('.collective-selector-trigger').click()
		}
	})
	cy.get('.collective-selector-popover-content').should('be.visible')
})

Cypress.Commands.add('openNewCollectiveModal', () => {
	Cypress.log()
	cy.openCollectiveSelector()
	cy.get('.collective-selector-popover-content button')
		.contains('New collective')
		.click()
	cy.get('.collective-name input[type="text"]').should('be.focused')
})

Cypress.Commands.add('openCollectiveMenu', (collectiveName) => {
	Cypress.log()
	cy.openCollectiveSelector()
	cy.get('.collectives_list_item')
		.contains('li', collectiveName)
		.click()
	cy.get('.collective-selector-actions .action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('openTrashedCollectiveMenu', (collectiveName) => {
	Cypress.log()
	cy.get('.dialog')
		.contains('tr', collectiveName)
		.find('.action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('clickMenuButton', (title) => {
	Cypress.log()
	cy.get('button.action-button, a.action-link')
		.contains(title)
		.click()
})

const FILE_LIST_SELECTOR = '.files-fileList a, [data-cy-files-list-row] [data-cy-files-list-row-name-link]'
Cypress.Commands.add('fileList', () => cy.get(FILE_LIST_SELECTOR))
