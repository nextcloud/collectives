Feature: collective

  Scenario: Create and share a collective
    When user "jane" creates collective "mycollective"
    And user "alice" is member of circle "mycollective" with admin "jane"
    Then user "jane" sees collective "mycollective"
    And user "alice" sees collective "mycollective"
    And user "jane" sees page "Readme" in "mycollective"
    And user "alice" sees page "Readme" in "mycollective"
    And user "john" doesn't see collective "mycollective"

  Scenario: Fail to trash a collective as simple member
    And user "alice" fails to trash collective "mycollective"

  Scenario: Fail to trash a foreign collective
    And user "john" fails to trash foreign collective "mycollective" with member "jane"

  Scenario: Trash an owned collective
    When user "jane" trashs collective "mycollective"
    Then user "jane" sees collective "mycollective" in trash
    And user "alice" doesn't see collective "mycollective" in trash

  Scenario: Restore an owned collective
    When user "jane" restores collective "mycollective"
    Then user "jane" sees collective "mycollective"
    And user "alice" sees collective "mycollective"

  Scenario: Trash an owned collective
    When user "jane" trashs collective "mycollective"

  Scenario: Fail to delete a collective as simple member
    And user "alice" fails to delete collective "mycollective" with admin "jane"

  Scenario: Fail to delete a foreign collective
    And user "john" fails to delete collective "mycollective" with admin "jane"

  Scenario: delete an owned collective with deleteTimestamp
    When user "jane" deletes collective "mycollective"
    Then user "jane" doesn't see collective "mycollective"
    And user "alice" doesn't see collective "mycollective"
