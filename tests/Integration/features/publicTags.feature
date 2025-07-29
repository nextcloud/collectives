# SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: tags

  Scenario: Prepare collective
    When user "jane" creates collective "BehatTagsPublicCollective"
    And user "jane" creates public share for "BehatTagsPublicCollective"
    And user "jane" sets editing permissions for collective share "BehatTagsPublicCollective"

  Scenario: Create and update tag in public collective share
    When anonymous creates tag "test1" with color "FF0000" for collective "BehatTagsPublicCollective" with owner "jane"
    Then anonymous fails to create existing tag "test1" with color "FFFFFF" for collective "BehatTagsPublicCollective" with owner "jane"
    And anonymous updates tag "test1" with color "FF0000" for collective "BehatTagsPublicCollective" with owner "jane"
    And anonymous sees tag "test1" with color "FF0000" for collective "BehatTagsPublicCollective" with owner "jane"

  Scenario: Fail to create, update and delete tag in public read-only collective share
    When user "jane" unsets editing permissions for collective share "BehatTagsPublicCollective"
    Then anonymous fails to create tag "test2" with color "FFFFFF" for collective "BehatTagsPublicCollective" with owner "jane"
    Then anonymous fails to update tag "test1" with color "FF0000" for collective "BehatTagsPublicCollective" with owner "jane"
    Then anonymous fails to delete tag "test1" for collective "BehatTagsPublicCollective" with owner "jane"

  Scenario: Delete tag
    When user "jane" sets editing permissions for collective share "BehatTagsPublicCollective"
    And anonymous deletes tag "test1" for collective "BehatTagsPublicCollective" with owner "jane"
    Then anonymous fails to see tag "test1" for collective "BehatTagsPublicCollective" with owner "jane"

  Scenario: Trash and delete collective and team
    Then user "jane" trashes collective "BehatTagsPublicCollective"
    And user "jane" deletes collective+team "BehatTagsPublicCollective"
