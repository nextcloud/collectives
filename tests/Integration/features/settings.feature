# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: settings

  Scenario: Set collectives user folder
    When user "john" sets setting "user_folder" to value "/other"
    Then user "john" gets setting "user_folder" with value "/other"
    And user "john" fails to set setting "nonexistent" to value "something"
