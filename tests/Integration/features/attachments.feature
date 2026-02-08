# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: attachments

  Scenario: Upload, rename delete and restore page attachment
    When user "jane" creates collective "BehatAttachmentsCollective"
    And user "jane" creates page "first" with parentPath "Readme.md" in "BehatAttachmentsCollective"
    And user "jane" uploads attachment "test.png" to page "first" in "BehatAttachmentsCollective"
    Then user "jane" sees attachment "test.png" with mimetype "image/png" for "first" in "BehatAttachmentsCollective"
    When user "jane" renames attachment "test.png" to "renamed.png" for page "first" in "BehatAttachmentsCollective"
    Then user "jane" sees attachment "renamed.png" with mimetype "image/png" for "first" in "BehatAttachmentsCollective"
    When user "jane" deletes attachment "renamed.png" for page "first" in "BehatAttachmentsCollective"
    Then user "jane" fails to see attachment "renamed.png" with mimetype "image/png" for "first" in "BehatAttachmentsCollective"
    When user "jane" restores deleted attachment for page "first" in "BehatAttachmentsCollective"
    Then user "jane" sees attachment "renamed.png" with mimetype "image/png" for "first" in "BehatAttachmentsCollective"

  Scenario: Not allowed to upload, rename, delete and restore page attachment for read-only user
    When user "jane" sets "edit" level in collective "BehatAttachmentsCollective" to "Admin"
    And user "alice" joins team "BehatAttachmentsCollective" with owner "jane"
    And user "jane" uploads attachment "test.png" to page "first" in "BehatAttachmentsCollective"
    Then user "alice" sees attachment "test.png" with mimetype "image/png" for "first" in "BehatAttachmentsCollective"
    And user "alice" fails to upload attachment "test.png" to page "first" in "BehatAttachmentsCollective"
    And user "alice" fails to rename attachment "test.png" to "renamed.png" for page "first" in "BehatAttachmentsCollective"
    And user "alice" fails to delete attachment "test.png" for page "first" in "BehatAttachmentsCollective"
    When user "jane" deletes attachment "renamed.png" for page "first" in "BehatAttachmentsCollective"
    Then user "alice" fails to restore deleted attachment for page "first" in "BehatAttachmentsCollective"

  Scenario: Trash and delete collective and team with all remaining pages
    Then user "jane" trashes and deletes collective "BehatAttachmentsCollective"
