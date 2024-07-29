# SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: collective

  Scenario: Create a collective and add members
    When user "jane" creates collective "BehatCollective"
    And user "alice" joins team "BehatCollective" with owner "jane"
    Then user "jane" sees collective "BehatCollective"
    And user "alice" sees collective "BehatCollective"
    And user "jane" sees pagePath "Readme.md" in "BehatCollective"
    And user "alice" sees pagePath "Readme.md" in "BehatCollective"
    And user "john" doesn't see collective "BehatCollective"

  Scenario: User with quota 0B sees collective
    When user "bob" has quota '0 B'
    And user "bob" joins team "BehatCollective" with owner "jane"
    Then user "bob" sees pagePath "Readme.md" in "BehatCollective"
    And user "bob" leaves team "BehatCollective" with owner "jane"
    And user "bob" has quota "default"

  Scenario: Edit page mode as admin and fail to edit page mode as member
    Then user "jane" sets pageMode for collective "BehatCollective" to "edit"
    And user "alice" fails to set pageMode for collective "BehatCollective" to "edit"

  Scenario: Fail to trash a collective as simple member
    And user "alice" fails to trash collective "BehatCollective"

  Scenario: Fail to trash a foreign collective
    And user "john" fails to trash foreign collective "BehatCollective" with member "jane"

  Scenario: Trash an owned collective
    When user "jane" trashes collective "BehatCollective"
    Then user "jane" sees collective "BehatCollective" in trash
    And user "jane" fails to create collective "BehatCollective"
    And user "alice" doesn't see collective "BehatCollective" in trash

  Scenario: Restore an owned collective
    When user "jane" restores collective "BehatCollective"
    Then user "jane" sees collective "BehatCollective"
    And user "alice" sees collective "BehatCollective"

  Scenario: Trash an owned collective
    When user "jane" trashes collective "BehatCollective"

  Scenario: Fail to delete a team via teams API
    Then user "jane" fails to delete team "BehatCollective"

  Scenario: Fail to delete a collective+team as admin
    When user "bob" joins team "BehatCollective" with owner "jane" with level "Admin"
    Then user "bob" fails to delete selfadmin collective+team "BehatCollective"

  Scenario: Fail to delete a collective as simple member
    And user "alice" fails to delete collective "BehatCollective" with admin "jane"
    And user "alice" fails to delete collective+team "BehatCollective" with admin "jane"

  Scenario: Fail to delete a foreign collective
    And user "john" fails to delete collective "BehatCollective" with admin "jane"
    And user "john" fails to delete collective+team "BehatCollective" with admin "jane"

  Scenario: Delete an owned trashed collective+team
    When user "jane" deletes collective+team "BehatCollective"
    Then user "jane" doesn't see collective "BehatCollective"
    And user "alice" doesn't see collective "BehatCollective"

  Scenario: Create and delete collective, keep team
    When user "jane" creates collective "BehatCollective2"
    And user "jane" trashes collective "BehatCollective2"
    And user "jane" deletes collective "BehatCollective2"
    And user "jane" is member of team "BehatCollective2"
    Then user "jane" deletes team "BehatCollective2"

  Scenario: Recreate a collective based on a leftover team
    When user "jane" creates collective "BehatPhoenixCollective"
    And user "alice" joins team "BehatPhoenixCollective" with owner "jane"
    And user "jane" trashes collective "BehatPhoenixCollective"
    And user "jane" deletes collective "BehatPhoenixCollective"
    And user "jane" is member of team "BehatPhoenixCollective"
    And user "jane" creates collective "BehatPhoenixCollective"
    Then user "jane" sees collective "BehatPhoenixCollective"
    And user "alice" sees collective "BehatPhoenixCollective"
    And user "jane" trashes collective "BehatPhoenixCollective"
    And user "jane" deletes collective+team "BehatPhoenixCollective"
