# SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: pages

  Scenario: Create collective and first page
    When user "jane" creates collective "BehatPagesCollective"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "BehatPagesCollective"
    And user "jane" creates page "secondpage" with parentPath "Readme.md" in "BehatPagesCollective"
    Then user "jane" sees pagePath "firstpage.md" in "BehatPagesCollective"

  Scenario: Upload and list attachment for page
    When user "jane" uploads attachment "test.png" to "firstpage" with file path "/" in "BehatPagesCollective"
    Then user "jane" sees attachment "test.png" with mimetype "image/png" for "firstpage" in "BehatPagesCollective"

  Scenario: Join collective (with pages) and create subpage
    When user "alice" joins team "BehatPagesCollective" with owner "jane"
    And user "alice" creates page "subpage" with parentPath "firstpage.md" in "BehatPagesCollective"
    Then user "jane" sees pagePath "firstpage/subpage.md" in "BehatPagesCollective"
    And user "jane" doesn't see pagePath "firstpage.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "firstpage/Readme.md" in "BehatPagesCollective"

  Scenario: Touch page
    When user "alice" touches page "firstpage" in "BehatPagesCollective"
    Then user "alice" last edited page "firstpage" in "BehatPagesCollective"

  Scenario: Create page with namespace conflict
    When user "alice" creates page "firstpage" with parentPath "Readme.md" in "BehatPagesCollective"
    Then user "alice" sees pagePath "firstpage (2).md" in "BehatPagesCollective"

  Scenario: Create another page with namespace conflict
    When user "jane" creates page "firstpage" with parentPath "Readme.md" in "BehatPagesCollective"
    Then user "jane" sees pagePath "firstpage (3).md" in "BehatPagesCollective"

  Scenario: Move page
    When user "jane" moves page "firstpage (2)" to "subpage2" with parentPath "firstpage/Readme.md" in "BehatPagesCollective"
    Then user "jane" sees pagePath "firstpage/subpage2.md" in "BehatPagesCollective"
    And user "jane" doesn't see pagePath "firstpage (2).md" in "BehatPagesCollective"

  Scenario: Copy page
    When user "jane" copies page "firstpage (3)" to "subpage3" with parentPath "firstpage/Readme.md" in "BehatPagesCollective"
    Then user "jane" sees pagePath "firstpage/subpage3.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "firstpage (3).md" in "BehatPagesCollective"

  Scenario: Fails to move/copy landingpage
    When user "jane" fails to move page "Landing page" to "newnamepage" with parentPath "Readme.md" in "BehatPagesCollective"
    When user "jane" fails to copy page "Landing page" to "newnamepage" with parentPath "Readme.md" in "BehatPagesCollective"

  Scenario: Change page emoji
    When user "jane" sets emoji for page "firstpage" to "🍏" in "BehatPagesCollective"
    And user "jane" sets emoji for page "firstpage" to "" in "BehatPagesCollective"

  Scenario: Change page full width
    When user "jane" sets full width for page "firstpage" to "true" in "BehatPagesCollective"
    And user "jane" sets full width for page "firstpage" to "false" in "BehatPagesCollective"

  Scenario: Change page subpageOrder
    When user "jane" sets subpageOrder for page "firstpage" to "[]" in "BehatPagesCollective"
    And user "jane" sets subpageOrder for page "firstpage" to "[1,2]" in "BehatPagesCollective"
    And user "jane" fails to set subpageOrder for page "firstpage" to "[invalid]" in "BehatPagesCollective"

  Scenario: Move parent page
    When user "jane" moves page "firstpage" to "parentpage" with parentPath "Readme.md" in "BehatPagesCollective"
    Then user "jane" sees pagePath "parentpage/Readme.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "parentpage/subpage.md" in "BehatPagesCollective"
    And user "jane" doesn't see pagePath "firstpage/subpage.md" in "BehatPagesCollective"
    And user "jane" doesn't see pagePath "firstpage/parentpage.md" in "BehatPagesCollective"

  Scenario: Move/copy page to another collective
    When user "jane" creates collective "BehatPagesCollective2"
    And user "jane" creates page "movethatpage1" with parentPath "Readme.md" in "BehatPagesCollective"
    And user "jane" creates page "movethatpage2" with parentPath "Readme.md" in "BehatPagesCollective"
    And user "jane" creates page "copythatpage" with parentPath "Readme.md" in "BehatPagesCollective"
    Then user "jane" moves page "movethatpage1" from collective "BehatPagesCollective" to collective "BehatPagesCollective2"
    And user "jane" doesn't see pagePath "movethatpage1.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "movethatpage1.md" in "BehatPagesCollective2"
    Then user "jane" moves page "movethatpage2" from collective "BehatPagesCollective" to collective "BehatPagesCollective2" with parentPath "movethatpage1.md"
    And user "jane" doesn't see pagePath "movethatpage2.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "movethatpage1/movethatpage2.md" in "BehatPagesCollective2"
    Then user "jane" copies page "copythatpage" from collective "BehatPagesCollective" to collective "BehatPagesCollective2"
    And user "jane" sees pagePath "copythatpage.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "copythatpage.md" in "BehatPagesCollective2"

  Scenario: Trash subpage
    When user "jane" trashes page "subpage" in "BehatPagesCollective"
    Then user "jane" doesn't see pagePath "parentpage/subpage.md" in "BehatPagesCollective"
    And user "jane" sees pagePath "parentpage/Readme.md" in "BehatPagesCollective"

  Scenario: Fail to restore+delete pages in read-only collective
    When user "alice" joins team "BehatPagesCollective" with owner "jane"
    And user "jane" sets "edit" level in collective "BehatPagesCollective" to "Admin"
    Then user "alice" fails to restore page "subpage" from trash in "BehatPagesCollective"
    When user "alice" fails to delete page "subpage" from trash in "BehatPagesCollective"

  Scenario: Restore subpage
    When user "jane" restores page "subpage" from trash in "BehatPagesCollective"
    Then user "jane" sees pagePath "parentpage/subpage.md" in "BehatPagesCollective"

  Scenario: Trash and restore a page with subpages
    When user "jane" trashes page "parentpage" in "BehatPagesCollective"
    And user "jane" doesn't see pagePath "parentpage/Readme.md" in "BehatPagesCollective"
    Then user "jane" restores page "parentpage" from trash in "BehatPagesCollective"

  Scenario: Fail to edit pages in read-only collective
    When user "john" joins team "BehatPagesCollective" with owner "jane"
    And user "jane" sets "edit" level in collective "BehatPagesCollective" to "Admin"
    Then user "john" fails to create page "johnspage" with parentPath "Readme.md" in "BehatPagesCollective"
    And user "john" fails to touch page "secondpage" in "BehatPagesCollective"
    And user "john" fails to move page "secondpage" to "newnamepage" with parentPath "Readme.md" in "BehatPagesCollective"
    And user "john" fails to set emoji for page "secondpage" to "🍏" in "BehatPagesCollective"
    And user "john" fails to set full width for page "secondpage" to "🍏" in "BehatPagesCollective"
    And user "john" fails to set subpageOrder for page "secondpage" to "[]" in "BehatPagesCollective"
    And user "john" fails to trash page "secondpage" in "BehatPagesCollective"

  Scenario: Trash and delete collective and team with all remaining pages
    Then user "jane" trashes collective "BehatPagesCollective"
    Then user "jane" trashes collective "BehatPagesCollective2"
    And user "jane" deletes collective+team "BehatPagesCollective"
    And user "jane" deletes collective+team "BehatPagesCollective2"
