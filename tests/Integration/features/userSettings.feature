# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: userSettings

  Scenario: Change userSetting "pageOrder" for a collective
    When user "jane" creates collective "BehatUserSettingsCollective"
    And user "jane" collective "BehatUserSettingsCollective" property "userPageOrder" is "0"
    And user "jane" collective "BehatUserSettingsCollective" property "userFavoritePages" is "[]"
    And user "jane" sets userSetting "pageOrder" for collective "BehatUserSettingsCollective" to "2"
    Then user "jane" collective "BehatUserSettingsCollective" property "userPageOrder" is "2"
    And user "jane" sets userSetting "favoritePages" for collective "BehatUserSettingsCollective" to "[1,2]"
    Then user "jane" collective "BehatUserSettingsCollective" property "userFavoritePages" is "[1,2]"

  Scenario: Trash and delete collective and team
    Then user "jane" trashes collective "BehatUserSettingsCollective"
    And user "jane" deletes collective+team "BehatUserSettingsCollective"
