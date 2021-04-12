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
    When user "jane" trashes collective "mycollective"
    Then user "jane" sees collective "mycollective" in trash
    And user "alice" doesn't see collective "mycollective" in trash

  Scenario: Restore an owned collective
    When user "jane" restores collective "mycollective"
    Then user "jane" sees collective "mycollective"
    And user "alice" sees collective "mycollective"

  Scenario: Trash an owned collective
    When user "jane" trashes collective "mycollective"

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

  Scenario: Create, trash and delete a collective with namespace conflict due to leftover circle
    When user "jane" creates collective "mycollective2"
    When user "jane" trashes collective "mycollective2"
    And user "jane" deletes collective "mycollective2"
    Then user "jane" fails to create collective "mycollective2"
    And user "jane" deletes cruft circle
