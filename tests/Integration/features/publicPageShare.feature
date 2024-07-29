# SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: publicPageShare

  Scenario: Create and share a single page publicly (read-only)
    When user "jane" creates collective "BehatPublicPageCollective"
    And user "jane" creates page "singlesharepage" with parentPath "Readme.md" in "BehatPublicPageCollective"
    And user "jane" creates public page share for page "singlesharepage" in "BehatPublicPageCollective"
    Then anonymous sees public page share "singlesharepage" in collective "BehatPublicPageCollective" with owner "jane"
    And anonymous sees pagePath "Readme.md" in public page share "singlesharepage" in collective "BehatPublicPageCollective" with owner "jane"
    And anonymous doesn't see pagePath "singlesharepage.md" in public page share "singlesharepage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Create and share a page with subpages publicly (read-only)
    When user "jane" creates page "sharefolderpage" with parentPath "Readme.md" in "BehatPublicPageCollective"
    And user "jane" creates page "subpage" with parentPath "sharefolderpage.md" in "BehatPublicPageCollective"
    And user "jane" creates public page share for page "sharefolderpage" in "BehatPublicPageCollective"
    Then anonymous sees public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"
    And anonymous sees pagePath "Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Upload and list attachment for page
    When user "jane" uploads attachment "test.png" to "subpage" with file path "/sharefolderpage/" in "BehatPublicPageCollective"
    Then anonymous sees attachment "test.png" with mimetype "image/png" for "subpage" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Fail to share a page if sharing permissions are missing
    When user "jane" sets "share" level in collective "BehatPublicPageCollective" to "Admin"
    And user "john" joins team "BehatPublicPageCollective" with owner "jane" with level "Moderator"
    Then user "john" fails to create public page share for page "singlesharepage" in "BehatPublicPageCollective"

  Scenario: Fail to create and trash page in read-only shared page
    Then anonymous fails to create page "secondsubpage" with parentPath "sharefolderpage/Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"
    And anonymous fails to set emoji for page "subpage" to "🍏" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"
    And anonymous fails to trash page "subpage" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Create page and edit emoji in editable shared page
    When user "jane" sets editing permissions for page share "sharefolderpage" in collective "BehatPublicPageCollective"
    Then anonymous creates page "secondsubpage" with parentPath "sharefolderpage/Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"
    Then anonymous sets emoji for page "secondsubpage" to "🍏" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Fail to create page outside shared page
    Then anonymous fails to create page "outsidepage" with parentPath "Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Fail to move page out of editable shared page
    Then anonymous fails to move page "secondsubpage" to "movedpage" with parentPath "Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Move page in editable shared page
    When anonymous moves page "secondsubpage" to "movedpage" with parentPath "sharefolderpage/Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"
    Then anonymous sees pagePath "movedpage.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"
    And anonymous doesn't see pagePath "secondsubpage.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Fail to trash page in editable shared page
    Then anonymous fails to trash page "subpage" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "jane"

  Scenario: Fail to create page in editable shared page if share owner misses editing permissions
    When user "jane" sets "share" level in collective "BehatPublicPageCollective" to "Member"
    And user "john" creates public page share for page "sharefolderpage" in "BehatPublicPageCollective"
    When user "john" sets editing permissions for page share "sharefolderpage" in collective "BehatPublicPageCollective"
    And anonymous creates page "page1" with parentPath "sharefolderpage/Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "john"
    And user "jane" sets "edit" level in collective "BehatPublicPageCollective" to "Admin"
    Then anonymous fails to create page "page2" with parentPath "sharefolderpage/Readme.md" in public page share "sharefolderpage" in collective "BehatPublicPageCollective" with owner "john"

  Scenario: Delete a public page share
    When user "jane" stores token for public page share "sharefolderpage" in collective "BehatPublicPageCollective"
    And user "jane" deletes public page share "sharefolderpage" in collective "BehatPublicPageCollective"
    Then anonymous fails to see public share with stored token

  Scenario: Trash and delete collective and team with all remaining pages
    Then user "jane" trashes collective "BehatPublicPageCollective"
    And user "jane" deletes collective+team "BehatPublicPageCollective"
