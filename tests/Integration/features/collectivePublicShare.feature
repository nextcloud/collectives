Feature: collectivePublicShare

  Scenario: Create and share a collective publically
    When user "jane" creates collective "Public Collective"
    And user "jane" creates public share for "Public Collective"
    Then anonymous sees public collective "Public Collective" with owner "jane"
    And anonymous sees pagePath "Readme.md" in public collective "Public Collective" with owner "jane"

  Scenario: Fail to create a second public share
    Then user "jane" fails to create public share for "Public Collective"

  Scenario: Delete a public share
    When user "jane" stores token for public share "Public Collective"
    And user "jane" deletes public share for "Public Collective"
    Then anonymous fails to see public collective "Public Collective" with stored token

  Scenario: Trash and delete collective and circle with all remaining pages
    Then user "jane" trashes collective "Public Collective"
    And user "jane" deletes collective+circle "Public Collective"
