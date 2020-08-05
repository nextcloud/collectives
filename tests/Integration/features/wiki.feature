Feature: wiki

  Scenario: Create and share a wiki
    When user "jane" creates wiki "mywiki"
    And user "alice" is member of circle "mywiki" with admin "jane"
    Then user "jane" sees wiki "mywiki"
    And user "alice" sees wiki "mywiki"
    And user "john" doesn't see wiki "mywiki"

  Scenario: Fail to delete a foreign wiki
    And user "john" fails to delete wiki "mywiki"

  Scenario: Delete an owned wiki
    When user "jane" deletes wiki "mywiki"
    Then user "jane" doesn't see wiki "mywiki"
    And user "alice" doesn't see wiki "mywiki"
