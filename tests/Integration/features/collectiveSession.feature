Feature: collectiveSession

  Scenario: Create a collective
    When user "jane" creates collective "BehatSessionCollective"
    And user "alice" joins team "BehatSessionCollective" with owner "jane"

  Scenario: Fail to create/update/close a session as non-member
    When user "bob" fails to create session for "BehatSessionCollective"
    When user "bob" fails to update session for "BehatSessionCollective" with token "invalid"
    When user "bob" fails to close session for "BehatSessionCollective" with token "invalid"

  Scenario: Create, update and close a session as member
    When user "jane" creates session for "BehatSessionCollective"
    Then user "jane" fails to update session for "BehatSessionCollective" with token "invalid"
    And user "jane" updates session for "BehatSessionCollective"
    And user "jane" closes session for "BehatSessionCollective"

  Scenario: Close a non-existing session as member
    When user "jane" closes session for "BehatSessionCollective" with token "invalid"

  Scenario: Create a session as member, fail to update after it got invalidated
    When user "jane" creates session for "BehatSessionCollective"
    And we wait for "95" seconds
    Then user "jane" fails to update session for "BehatSessionCollective"

  Scenario: Delete collective
    When user "jane" trashes collective "BehatSessionCollective"
    And user "jane" deletes collective+team "BehatSessionCollective"
