Feature: collective

  Scenario: Create a collective and add members
    When user "jane" creates collective "mycollective"
    And user "alice" joins circle "mycollective" with owner "jane"
    Then user "jane" sees collective "mycollective"
    And user "alice" sees collective "mycollective"
    And user "jane" sees pagePath "Readme.md" in "mycollective"
    And user "alice" sees pagePath "Readme.md" in "mycollective"
    And user "john" doesn't see collective "mycollective"

  Scenario: Fail to trash a collective as simple member
    And user "alice" fails to trash collective "mycollective"

  Scenario: Fail to trash a foreign collective
    And user "john" fails to trash foreign collective "mycollective" with member "jane"

  Scenario: Trash an owned collective
    When user "jane" trashes collective "mycollective"
    Then user "jane" sees collective "mycollective" in trash
    And user "jane" fails to create collective "mycollective"
    And user "alice" doesn't see collective "mycollective" in trash

  Scenario: Restore an owned collective
    When user "jane" restores collective "mycollective"
    Then user "jane" sees collective "mycollective"
    And user "alice" sees collective "mycollective"

  Scenario: Trash an owned collective
    When user "jane" trashes collective "mycollective"

  Scenario: Fail to delete a collective+circle as admin
    When user "bob" joins circle "mycollective" with owner "jane" with level "Admin"
    Then user "bob" fails to delete selfadmin collective+circle "mycollective"

  Scenario: Fail to delete a collective as simple member
    And user "alice" fails to delete collective "mycollective" with admin "jane"
    And user "alice" fails to delete collective+circle "mycollective" with admin "jane"

  Scenario: Fail to delete a foreign collective
    And user "john" fails to delete collective "mycollective" with admin "jane"
    And user "john" fails to delete collective+circle "mycollective" with admin "jane"

  Scenario: Delete an owned collective+circle with deleteTimestamp
    When user "jane" deletes collective+circle "mycollective"
    Then user "jane" doesn't see collective "mycollective"
    And user "alice" doesn't see collective "mycollective"

  Scenario: Recreate a collective based on a leftover circle
    When user "jane" creates collective "Phoenix"
    And user "alice" joins circle "Phoenix" with owner "jane"
    And user "jane" trashes collective "Phoenix"
    And user "jane" deletes collective "Phoenix"
    And user "jane" is member of circle "Phoenix"
    And user "jane" creates collective "Phoenix"
    Then user "jane" sees collective "Phoenix"
    And user "alice" sees collective "Phoenix"
    And user "jane" trashes collective "Phoenix"
    And user "jane" deletes collective+circle "Phoenix"
