Feature: collectiveUserSettings

  Scenario: Change userSetting "pageOrder" for a collective
    When user "jane" creates collective "User Settings Collective"
    And user "jane" collective "User Settings Collective" property "userPageOrder" is "0"
    And user "jane" sets userSetting "pageOrder" for collective "User Settings Collective" to "2"
    Then user "jane" collective "User Settings Collective" property "userPageOrder" is "2"

  Scenario: Trash and delete collective and circle
    Then user "jane" trashes collective "User Settings Collective"
    And user "jane" deletes collective+circle "User Settings Collective"
