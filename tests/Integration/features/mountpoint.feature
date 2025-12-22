# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: mountpoint

  Scenario: Set edit and share permissions for collective and check mountpoint permissions
    When user "jane" creates collective "BehatMountPoint"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "BehatMountPoint"
    And user "jane" uploads attachment "test.png" to "firstpage" with file path "/" in "BehatMountPoint"
    And user "jane" sets "edit" level in collective "BehatMountPoint" to "Admin"
    And user "jane" sets "share" level in collective "BehatMountPoint" to "Moderator"
    And user "john" joins team "BehatMountPoint" with owner "jane" with level "Admin"
    And user "alice" joins team "BehatMountPoint" with owner "jane" with level "Moderator"
    And user "bob" joins team "BehatMountPoint" with owner "jane"
    Then user "jane" has webdav access to "BehatMountPoint" with permissions "RMGDNVCK"
    And user "john" has webdav access to "BehatMountPoint" with permissions "RMGDNVCK"
    And user "alice" has webdav access to "BehatMountPoint" with permissions "RMG"
    And user "bob" has webdav access to "BehatMountPoint" with permissions "MG"

  Scenario: Change collectives user folder for user
    When user "bob" sets setting "user_folder" to value "/some/folder"
    Then user "jane" has webdav access to "BehatMountPoint" with permissions "RMGDNVCK"
    And user "john" has webdav access to "BehatMountPoint" with permissions "RMGDNVCK"
    And user "alice" has webdav access to "BehatMountPoint" with permissions "RMG"
    And user "bob" has webdav access to "BehatMountPoint" with permissions "MG"
    Then user "bob" sets setting "user_folder" to value "/Collectives"

  Scenario: Trash page via webdav
    When user "bob" fails to trash page "firstpage" via webdav in "BehatMountPoint"
    And user "jane" trashes page "firstpage" via webdav in "BehatMountPoint"
    Then user "jane" doesn't see pagePath "firstpage.md" in "BehatMountPoint"

  Scenario: Fail to restore+delete page in read-only collective via webdav
    Then user "bob" fails to restore page "firstpage" from trash via webdav in "BehatMountPoint"
    And user "bob" fails to delete page "firstpage" from trash via webdav in "BehatMountPoint"

  Scenario: Restore page via webdav
    When user "jane" restores page "firstpage" from trash via webdav in "BehatMountPoint"
    Then user "jane" sees pagePath "firstpage.md" in "BehatMountPoint"
    And user "jane" sees attachment "test.png" with mimetype "image/png" for "firstpage" in "BehatMountPoint"

  Scenario: Trash and delete page via webdav
    When user "jane" trashes page "firstpage" via webdav in "BehatMountPoint"
    And user "jane" deletes page "firstpage" from trash via webdav in "BehatMountPoint"
    Then user "jane" fails to see pagePath "firstpage.md" in "BehatMountPoint"

  Scenario: Editable public share of collective permissions is read-write
    When user "jane" sets "edit" level in collective "BehatMountPoint" to "Member"
    And user "jane" sets "share" level in collective "BehatMountPoint" to "Member"
    And user "jane" creates public share for "BehatMountPoint"
    And user "jane" sets editing permissions for collective share "BehatMountPoint"
    Then public share with owner "jane" has webdav access to "BehatMountPoint" with permissions "RGDNVCK"

  Scenario: Editable public share of collective with admin-only permissions is read-only
    When user "jane" sets "edit" level in collective "BehatMountPoint" to "Admin"
    Then public share with owner "jane" has webdav access to "BehatMountPoint" with permissions "RG"

  Scenario: Trash and delete collective and team
    Then user "jane" trashes collective "BehatMountPoint"
    And user "jane" deletes collective+team "BehatMountPoint"
