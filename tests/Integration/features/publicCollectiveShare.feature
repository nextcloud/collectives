# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: publicCollectiveShare

  Scenario: Create and share a collective publicly (read-only)
    When user "jane" creates collective "BehatPublicCollective"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "BehatPublicCollective"
    And user "jane" creates public share for "BehatPublicCollective"
    Then anonymous sees public collective "BehatPublicCollective" with owner "jane"
    And anonymous sees pagePath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Upload and list attachment for page
    When user "jane" uploads attachment "test.png" to "firstpage" with file path "/" in "BehatPublicCollective"
    Then anonymous sees attachment "test.png" with mimetype "image/png" for "firstpage" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Create a second public share
    Then user "jane" creates public share for "BehatPublicCollective"

  Scenario: Fail to share a collective if sharing permissions are missing
    When user "jane" sets "share" level in collective "BehatPublicCollective" to "Admin"
    And user "john" joins team "BehatPublicCollective" with owner "jane" with level "Moderator"
    Then user "john" fails to create public share for "BehatPublicCollective"

  Scenario: Fail to create and trash page in read-only shared collective
    Then anonymous fails to create page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    Then anonymous fails to set emoji for page "firstpage" to "🍏" in public collective "BehatPublicCollective" with owner "jane"
    And anonymous fails to trash page "firstpage" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Create page and edit emoji in editable shared collective
    When user "jane" sets editing permissions for collective share "BehatPublicCollective"
    Then anonymous creates page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    Then anonymous sets emoji for page "secondpage" to "🍏" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Move page
    When anonymous moves page "firstpage" to "movedpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    Then anonymous sees pagePath "movedpage.md" in public collective "BehatPublicCollective" with owner "jane"
    And anonymous doesn't see pagePath "firstpage.md" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Copy page
    When anonymous copies page "movedpage" to "copiedpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    Then anonymous sees pagePath "copiedpage.md" in public collective "BehatPublicCollective" with owner "jane"
    And anonymous sees pagePath "movedpage.md" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Trash page
    When anonymous trashes page "secondpage" in public collective "BehatPublicCollective" with owner "jane"
    Then user "jane" doesn't see pagePath "secondpage.md" in "BehatPublicCollective"

  Scenario: Fail to restore+delete pages in read-only collective
    When user "jane" unsets editing permissions for collective share "BehatPublicCollective"
    Then anonymous fails to restore page "secondpage" from trash in public collective "BehatPublicCollective" with owner "jane"
    And anonymous fails to delete page "secondpage" from trash in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Restore, trash and delete subpage
    When user "jane" sets editing permissions for collective share "BehatPublicCollective"
    And anonymous restores page "secondpage" from trash in public collective "BehatPublicCollective" with owner "jane"
    And user "jane" sees pagePath "secondpage.md" in "BehatPublicCollective"
    And anonymous trashes page "secondpage" in public collective "BehatPublicCollective" with owner "jane"
    And anonymous deletes page "secondpage" from trash in public collective "BehatPublicCollective" with owner "jane"
    Then user "jane" doesn't see pagePath "secondpage.md" in "BehatPublicCollective"

  Scenario: Fail to create and trash page in editable shared collective if share owner misses editing permissions
    When user "jane" sets "share" level in collective "BehatPublicCollective" to "Member"
    And user "john" creates public share for "BehatPublicCollective"
    And user "john" sets editing permissions for collective share "BehatPublicCollective"
    And anonymous creates page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "john"
    And user "jane" sets "edit" level in collective "BehatPublicCollective" to "Admin"
    Then anonymous fails to create page "thirdpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "john"
    And anonymous fails to trash page "secondpage" in public collective "BehatPublicCollective" with owner "john"

  Scenario: Delete a public share
    When user "jane" stores token for public share "BehatPublicCollective"
    And user "jane" deletes public share for "BehatPublicCollective"
    Then anonymous fails to see public share with stored token

  Scenario: Trash and delete collective and team with all remaining pages
    Then user "jane" trashes collective "BehatPublicCollective"
    And user "jane" deletes collective+team "BehatPublicCollective"
