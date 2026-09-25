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
