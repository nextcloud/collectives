Feature: userSettings

  Scenario: Change userSetting "pageOrder" for a collective
    When user "jane" creates collective "BehatUserSettingsCollective"
    And user "jane" collective "BehatUserSettingsCollective" property "userPageOrder" is "0"
    And user "jane" sets userSetting "pageOrder" for collective "BehatUserSettingsCollective" to "2"
    Then user "jane" collective "BehatUserSettingsCollective" property "userPageOrder" is "2"

  Scenario: Trash and delete collective and circle
    Then user "jane" trashes collective "BehatUserSettingsCollective"
    And user "jane" deletes collective+circle "BehatUserSettingsCollective"
