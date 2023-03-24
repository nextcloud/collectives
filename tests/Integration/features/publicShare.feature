Feature: publicShare

  Scenario: Create and share a collective publically (read-only)
    When user "jane" creates collective "BehatPublicCollective"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "BehatPublicCollective"
    And user "jane" creates public share for "BehatPublicCollective"
    Then anonymous sees public collective "BehatPublicCollective" with owner "jane"
    And anonymous sees pagePath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Upload and list attachment for page
    When user "jane" uploads attachment "test.png" to "firstpage" in "BehatPublicCollective"
    Then anonymous sees attachment "test.png" with mimetype "image/png" for "firstpage" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Fail to create a second public share
    Then user "jane" fails to create public share for "BehatPublicCollective"

  Scenario: Fail to share a collective if sharing permissions are missing
    When user "jane" sets "share" level in collective "BehatPublicCollective" to "Admin"
    And user "john" joins circle "BehatPublicCollective" with owner "jane" with level "Moderator"
    Then user "john" fails to create public share for "BehatPublicCollective"

  Scenario: Fail to create and delete page in read-only shared collective
    Then anonymous fails to create page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    Then anonymous fails to set emoji for page "firstpage" to "🍏" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    And anonymous fails to delete page "firstpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Create page, edit emoji and delete page in editable shared collective
    When user "jane" sets editing permissions for collective "BehatPublicCollective"
    Then anonymous creates page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    Then anonymous sets emoji for page "secondpage" to "🍏" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"
    And anonymous deletes page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "jane"

  Scenario: Fail to create and delete page in editable shared collective if share owner misses editing permissions
    When user "jane" sets "share" level in collective "BehatPublicCollective" to "Member"
    And user "john" creates public share for "BehatPublicCollective"
    And user "john" sets editing permissions for collective "BehatPublicCollective"
    And anonymous creates page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "john"
    And user "jane" sets "edit" level in collective "BehatPublicCollective" to "Admin"
    Then anonymous fails to create page "thirdpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "john"
    And anonymous fails to delete page "secondpage" with parentPath "Readme.md" in public collective "BehatPublicCollective" with owner "john"

  Scenario: Delete a public share
    When user "jane" stores token for public share "BehatPublicCollective"
    And user "jane" deletes public share for "BehatPublicCollective"
    Then anonymous fails to see public collective "BehatPublicCollective" with stored token

  Scenario: Trash and delete collective and circle with all remaining pages
    Then user "jane" trashes collective "BehatPublicCollective"
    And user "jane" deletes collective+circle "BehatPublicCollective"
