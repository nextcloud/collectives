# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: templates

  Scenario: Create collective
    When user "jane" creates collective "BehatTemplatesCollective"

  Scenario: Create template and page from template
    When user "jane" creates template "newtemplate" in "BehatTemplatesCollective"
    Then user "jane" sees templateName "newtemplate" in "BehatTemplatesCollective"
    And user "jane" creates page "anotherpage" with parentPath "Readme.md" from template "newtemplate" in "BehatTemplatesCollective"
    Then user "jane" sees pagePath "anotherpage.md" in "BehatTemplatesCollective"

  Scenario: Change template emoji
    When user "jane" sets emoji for template "newtemplate to "🍏" in "BehatTemplatesCollective"
    And user "jane" sets emoji for template "newtemplate" to "" in "BehatTemplatesCollective"

  Scenario: Rename template
    When user "jane" renames template "newtemplate to "newtemplate2" in "BehatTemplatesCollective"
    Then user "jane" fails to see templateName "newtemplate" in "BehatTemplatesCollective"
    Then user "jane" sees templateName "newtemplate2" in "BehatTemplatesCollective"

  Scenario: Delete template
    When user "jane" deletes template "newtemplate2" from "BehatTemplatesCollective"
    Then user "jane" fails to see templateName "newtemplate2" in "BehatTemplatesCollective"

  Scenario: Trash and delete collective and team
    Then user "jane" trashes collective "BehatTemplatesCollective"
    And user "jane" deletes collective+team "BehatTemplatesCollective"
