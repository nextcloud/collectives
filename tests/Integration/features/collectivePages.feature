Feature: collectivePages

  Scenario: Create collective and first page
    When user "jane" creates collective "mycollective"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "mycollective"
    And user "jane" creates page "secondpage" with parentPath "Readme.md" in "mycollective"
    Then user "jane" sees pagePath "firstpage.md" in "mycollective"

  Scenario: Share collective (with pages) and create subpage
    When user "alice" joins circle "mycollective" with owner "jane"
    And user "alice" creates page "subpage" with parentPath "firstpage.md" in "mycollective"
    Then user "jane" sees pagePath "firstpage/subpage.md" in "mycollective"
    And user "jane" doesn't see pagePath "firstpage.md" in "mycollective"
    And user "jane" sees pagePath "firstpage/Readme.md" in "mycollective"

  Scenario: Touch page
    When user "alice" touches page "firstpage" with parentPath "Readme.md" in "mycollective"
    Then user "alice" last edited page "firstpage" in "mycollective"

  Scenario: Create page with namespace conflict
    When user "alice" creates page "firstpage" with parentPath "Readme.md" in "mycollective"
    Then user "alice" sees pagePath "firstpage (2).md" in "mycollective"

  Scenario: Create another page with namespace conflict
    When user "jane" creates page "firstpage" with parentPath "Readme.md" in "mycollective"
    Then user "jane" sees pagePath "firstpage (3).md" in "mycollective"

  Scenario: Rename page
    When user "jane" renames page "firstpage (2)" to "subpage2" with parentPath "firstpage/Readme.md" in "mycollective"
    Then user "jane" sees pagePath "firstpage/subpage2.md" in "mycollective"
    And user "jane" doesn't see pagePath "firstpage (2).md" in "mycollective"

  Scenario: Change page emoji
    When user "jane" sets emoji for page "firstpage" to "🍏" with parentPath "Readme.md" in "mycollective"
    Then user "jane" sets emoji for page "firstpage" to "" with parentPath "Readme.md" in "mycollective"

  Scenario: Fail to delete a page with subpages
    When user "jane" fails to delete page "firstpage" with parentPath "Readme.md" in "mycollective"
    Then user "jane" sees pagePath "firstpage/Readme.md" in "mycollective"

  Scenario: Rename parent page
    When user "jane" renames page "firstpage" to "parentpage" with parentPath "Readme.md" in "mycollective"
    Then user "jane" sees pagePath "parentpage/Readme.md" in "mycollective"
    And user "jane" sees pagePath "parentpage/subpage.md" in "mycollective"
    And user "jane" doesn't see pagePath "firstpage/subpage.md" in "mycollective"
    And user "jane" doesn't see pagePath "firstpage/parentpage.md" in "mycollective"

  Scenario: Create and use template page
    When user "jane" creates page "Template" with parentPath "Readme.md" in "mycollective"
    And user "jane" creates page "Subtemplate" with parentPath "Template.md" in "mycollective"
    And user "jane" creates page "anotherpage" with parentPath "Readme.md" in "mycollective"
    Then user "jane" sees pagePath "anotherpage/Subtemplate.md" in "mycollective"

  Scenario: Delete all subpages
    When user "jane" deletes page "subpage" with parentPath "parentpage/Readme.md" in "mycollective"
    And user "jane" deletes page "subpage2" with parentPath "parentpage/Readme.md" in "mycollective"
    Then user "jane" doesn't see pagePath "parentpage/Readme.md" in "mycollective"
    And user "jane" sees pagePath "parentpage.md" in "mycollective"

  Scenario: Fail to edit pages in read-only collective
    When user "john" joins circle "mycollective" with owner "jane"
    And user "jane" sets "edit" level in collective "mycollective" to "Admin"
    Then user "john" fails to create page "johnspage" with parentPath "Readme.md" in "mycollective"
    And user "john" fails to touch page "secondpage" with parentPath "Readme.md" in "mycollective"
    And user "john" fails to rename page "secondpage" to "newnamepage" with parentPath "Readme.md" in "mycollective"
    And user "john" fails to set emoji for page "secondpage" to "🍏" with parentPath "Readme.md" in "mycollective"
    And user "john" fails to delete page "secondpage" with parentPath "Readme.md" in "mycollective"

  Scenario: Trash and delete collective and circle with all remaining pages
    Then user "jane" trashes collective "mycollective"
    And user "jane" deletes collective+circle "mycollective"
