Feature: collective

  Scenario: Create and share a collective
    When user "jane" creates collective "mycollective"
    And user "alice" is member of circle "mycollective" with admin "jane"
    Then user "jane" sees collective "mycollective"
    And user "alice" sees collective "mycollective"
    And user "jane" sees page "Readme" in "mycollective"
    And user "alice" sees page "Readme" in "mycollective"
    And user "john" doesn't see collective "mycollective"

  Scenario: Fail to delete a foreign collective
    And user "john" fails to delete foreign collective "mycollective" with member "jane"

  Scenario: Fail to delete a collective as simple member
    And user "alice" fails to delete collective "mycollective"

  Scenario: Delete an owned collective
    When user "jane" deletes collective "mycollective"
    Then user "jane" doesn't see collective "mycollective"
    And user "alice" doesn't see collective "mycollective"
