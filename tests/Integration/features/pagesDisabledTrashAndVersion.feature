# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: pagesDisabledTrashbin

  Scenario: Disable trashbin and versions apps
    When app "files_trashbin" is "disabled"
    And app "files_versions" is "disabled"

  Scenario: Create collective and first page
    When user "jane" creates collective "BehatPagesDisabledTrashAndVersionsCollective"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "BehatPagesDisabledTrashAndVersionsCollective"
    And user "jane" creates page "secondpage" with parentPath "Readme.md" in "BehatPagesDisabledTrashAndVersionsCollective"
    Then user "jane" sees pagePath "firstpage.md" in "BehatPagesDisabledTrashAndVersionsCollective"

  Scenario: Trash page, fail to restore+delete pages with disabled trashbin
    When user "jane" trashes page "firstpage" in "BehatPagesDisabledTrashAndVersionsCollective"
    And user "jane" doesn't see pagePath "firstpage.md" in "BehatPagesDisabledTrashAndVersionsCollective"
    Then user "jane" fails to restore page "firstpage" from trash in "BehatPagesDisabledTrashAndVersionsCollective"
    When user "jane" fails to delete page "firstpage" from trash in "BehatPagesDisabledTrashAndVersionsCollective"

  Scenario: Trash and delete collective and team with all remaining pages
    Then user "jane" trashes collective "BehatPagesDisabledTrashAndVersionsCollective"
    And user "jane" deletes collective+team "BehatPagesDisabledTrashAndVersionsCollective"

  Scenario: Enable trashbin and versions apps
    When app "files_trashbin" is "enabled"
    And app "files_versions" is "enabled"
