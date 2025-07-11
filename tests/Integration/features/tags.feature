# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: tags

  Scenario: Prepare collective
    When user "jane" creates collective "BehatTagsCollective"
    And user "jane" sets "edit" level in collective "BehatTagsCollective" to "Admin"
    And user "alice" joins team "BehatTagsCollective" with owner "jane"

  Scenario: Create and update tag for a collective
    When user "jane" creates tag "test1" with color "FFFFFF" for collective "BehatTagsCollective"
    Then user "jane" fails to create existing tag "test1" with color "FFFFFF" for collective "BehatTagsCollective"
    And user "jane" updates tag "test1" with color "FF0000" for collective "BehatTagsCollective"
    Then user "alice" sees tag "test1" with color "FF0000" for collective "BehatTagsCollective"

  Scenario: Fail to create, update and delete tag without editing permission
    Then user "alice" fails to create tag "test2" with color "FFFFFF" for collective "BehatTagsCollective"
    Then user "alice" fails to update tag "test1" with color "FF0000" for collective "BehatTagsCollective"
    Then user "alice" fails to delete tag "test1" for collective "BehatTagsCollective"

  Scenario: Delete tag
    When user "jane" deletes tag "test1" for collective "BehatTagsCollective"
    Then user "jane" fails to see tag "test1" for collective "BehatTagsCollective"

  Scenario: Trash and delete collective and team
    Then user "jane" trashes collective "BehatTagsCollective"
    And user "jane" deletes collective+team "BehatTagsCollective"
