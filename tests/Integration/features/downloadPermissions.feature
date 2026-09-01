# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: Download permissions

  Background:
    Given user "jane" creates collective "BehatCollective"
    And user "alice" joins team "BehatCollective" with owner "jane"
    And user "jane" sets content of file "test.txt" to "Hello" in collective "BehatCollective"

  Scenario: Admin can download and member cannot when set to admins only
    When user "jane" sets custom setting "downloadPermissionLevel" to value "8" for collective "BehatCollective"
    Then user "jane" can download "test.txt" from collective "BehatCollective"
    And user "alice" cannot download "test.txt" from collective "BehatCollective"

  Scenario: Member can download when all members are allowed
    When user "jane" sets custom setting "downloadPermissionLevel" to value "1" for collective "BehatCollective"
    Then user "alice" can download "test.txt" from collective "BehatCollective"

  Scenario: Simple member cannot change download permission
    When user "alice" fails to set custom setting "downloadPermissionLevel" to value "1" for collective "BehatCollective"

  Scenario: Download permissions do not prevent members from previewing pages
    When user "jane" creates page "preview" with parentPath "Readme.md" in "BehatCollective"
    And user "jane" sets content of file "preview.md" to "Hello preview" in collective "BehatCollective"
    And user "jane" sets custom setting "downloadPermissionLevel" to value "8" for collective "BehatCollective"
    Then user "alice" sees preview "Hello preview" for page "preview" in collective "BehatCollective" owned by "jane"
    And user "alice" cannot download "preview.md" from collective "BehatCollective"
    And user "alice" cannot download an archive of collective "BehatCollective"
    And user "bob" cannot preview page "preview" in collective "BehatCollective" owned by "jane"

  Scenario: DAV home listing works without the teams app
    When app "circles" is "disabled"
    Then user "john" can list the DAV home folder
    When app "circles" is "enabled"
