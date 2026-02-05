# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: publicAttachments

  Scenario: Upload, rename and delete page attachment in public share
    When user "jane" creates collective "BehatPublicAttachmentsCollective"
    And user "jane" creates page "first" with parentPath "Readme.md" in "BehatPublicAttachmentsCollective"
    And user "jane" creates public share for "BehatPublicAttachmentsCollective"
    And user "jane" sets editing permissions for collective share "BehatPublicAttachmentsCollective"
    And anonymous uploads attachment "test.png" to page "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    Then anonymous sees attachment "test.png" with mimetype "image/png" for "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    When anonymous renames attachment "test.png" to "renamed.png" for page "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    Then anonymous sees attachment "renamed.png" with mimetype "image/png" for "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    When anonymous deletes attachment "renamed.png" for page "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    Then anonymous fails to see attachment "renamed.png" with mimetype "image/png" for "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"

  Scenario: Not allowed to upload, rename and delete page attachment for read-only user in public share
    And user "jane" unsets editing permissions for collective share "BehatPublicAttachmentsCollective"
    And user "jane" uploads attachment "test.png" to page "first" in "BehatPublicAttachmentsCollective"
    Then anonymous sees attachment "test.png" with mimetype "image/png" for "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    And anonymous fails to rename attachment "test.png" to "renamed.png" for page "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"
    And anonymous fails to delete attachment "test.png" for page "first" in public collective "BehatPublicAttachmentsCollective" with owner "jane"

  Scenario: Trash and delete collective and team with all remaining pages
    Then user "jane" trashes and deletes collective "BehatPublicAttachmentsCollective"
