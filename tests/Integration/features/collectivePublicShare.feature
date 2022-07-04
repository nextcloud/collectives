Feature: collectivePublicShare

  Scenario: Create and share a collective publically (read-only)
    When user "jane" creates collective "Public Collective"
    And user "jane" creates page "firstpage" with parentPath "Readme.md" in "Public Collective"
    And user "jane" creates public share for "Public Collective"
    Then anonymous sees public collective "Public Collective" with owner "jane"
    And anonymous sees pagePath "Readme.md" in public collective "Public Collective" with owner "jane"

  Scenario: Fail to create a second public share
    Then user "jane" fails to create public share for "Public Collective"

  Scenario: Fail to share a collective if sharing permissions are missing
    When user "jane" sets "share" level in collective "Public Collective" to "Admin"
    And user "john" joins circle "Public Collective" with owner "jane" with level "Moderator"
    Then user "john" fails to create public share for "Public Collective"

  Scenario: Fail to create and delete page in read-only shared collective
    Then anonymous fails to create page "secondpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "jane"
    Then anonymous fails to set emoji for page "firstpage" to "🍏" with parentPath "Readme.md" in public collective "Public Collective" with owner "jane"
    And anonymous fails to delete page "firstpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "jane"

  Scenario: Create page, edit emoji and delete page in editable shared collective
    When user "jane" sets editing permissions for collective "Public Collective"
    Then anonymous creates page "secondpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "jane"
    Then anonymous sets emoji for page "secondpage" to "🍏" with parentPath "Readme.md" in public collective "Public Collective" with owner "jane"
    And anonymous deletes page "secondpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "jane"

  Scenario: Fail to create and delete page in editable shared collective if share owner misses editing permissions
    When user "jane" sets "share" level in collective "Public Collective" to "Member"
    And user "john" creates public share for "Public Collective"
    And user "john" sets editing permissions for collective "Public Collective"
    And anonymous creates page "secondpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "john"
    And user "jane" sets "edit" level in collective "Public Collective" to "Admin"
    Then anonymous fails to create page "thirdpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "john"
    And anonymous fails to delete page "secondpage" with parentPath "Readme.md" in public collective "Public Collective" with owner "john"

  Scenario: Delete a public share
    When user "jane" stores token for public share "Public Collective"
    And user "jane" deletes public share for "Public Collective"
    Then anonymous fails to see public collective "Public Collective" with stored token

  Scenario: Trash and delete collective and circle with all remaining pages
    Then user "jane" trashes collective "Public Collective"
    And user "jane" deletes collective+circle "Public Collective"
