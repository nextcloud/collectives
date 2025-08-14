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
	cy.contains('.app-content-list-item a', pageName).click()
})

Cypress.Commands.add('openPageMenu', (pageName) => {
	Cypress.log()
	cy.contains('.app-content-list-item', pageName)
		.find('.action-item__menutoggle')
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

Cypress.Commands.add('openCollectiveMenu', (collectiveName) => {
	Cypress.log()
	cy.get('.collectives_list_item')
		.contains('li', collectiveName)
		.find('.action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('openTrashedCollectiveMenu', (collectiveName) => {
	Cypress.log()
	cy.get('.collectives_trash_list_item')
		.contains('li', collectiveName)
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

Cypress.Commands.add('openFile', (name) => cy.fileList().contains(name).click())
