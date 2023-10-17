Cypress.Commands.add('openApp', (appName) => {
	cy.get(`nav.app-menu li[data-app-id="${appName}"] a`).click()
})

Cypress.Commands.add('openPage', (pageName) => {
	cy.contains('.app-content-list-item a', pageName).click()
})

Cypress.Commands.add('openPageMenu', (pageName) => {
	cy.contains('.app-content-list-item', pageName)
		.find('.action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('openCollective', (collectiveName) => {
	cy.get(`.collectives_list_item a[title="${collectiveName}"]`)
		.click()
})

Cypress.Commands.add('openCollectiveMenu', (collectiveName) => {
	cy.get('.collectives_list_item')
		.contains('li', collectiveName)
		.find('.action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('openTrashedCollectiveMenu', (collectiveName) => {
	cy.get('.collectives_trash_list_item')
		.contains('li', collectiveName)
		.find('.action-item__menutoggle')
		.click({ force: true })
})

Cypress.Commands.add('clickMenuButton', (title) => {
	cy.get('button.action-button')
		.contains(title)
		.click()
})
