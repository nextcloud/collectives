describe('Links', function() {
	before(function() {
		cy.login('bob', 'bob', '/apps/collectives')
		cy.seedCollective('Our Garden')
	})

	describe('when creating a page', function() {
		before(function() {
			cy.login('bob', 'bob', '/apps/collectives/Our%20Garden')
		})
		it('to the collectives landing page', function() {
			cy.contains('.app-content-list-item', 'Our Garden').find('button.icon-add').click({ force: true })
			cy.contains('.app-content-list-item', 'New Page')
			cy.focused().should('have.attr', 'placeholder')
			cy.focused().type('Tools{enter}')
			cy.get('#titleform input').should('not.be', 'focussed')
			cy.get('#editor-wrapper')
			cy.focused().type('In the garden')
			cy.focused().type('{selectAll}')
			cy.contains('button', 'Link file').click()
			cy.contains('.filename', 'Readme.md').click()
			cy.contains('button', 'Choose').click()
			cy.focused().type('{end} we have some tools.')
			cy.get('.save-status', { timeout: 10000 })
				.should('contain', 'Saved')
			cy.contains('button', 'Done').click()
			cy.contains('#text-container a', 'In the garden').click()
			cy.get('#titleform input').should('have.value', 'Our Garden')
			cy.get('#titleform input').should('not.be', 'focussed')
		})
	})

	after(function() {
		cy.deleteCollective('Our Garden')
	})
})
