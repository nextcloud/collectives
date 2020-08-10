Feature: wikiPages

  Scenario: Create wiki and first page
    When user "jane" creates wiki "mywiki"
    And user "jane" creates page "firstpage" in "mywiki"
    Then user "jane" sees page "firstpage" in "mywiki"

  Scenario: Share wiki (with pages) and create second page
    When user "alice" is member of circle "mywiki" with admin "jane"
    And user "alice" creates page "secondpage" in "mywiki"
    Then user "alice" sees page "secondpage" in "mywiki"
    And user "jane" sees page "secondpage" in "mywiki"

  Scenario: Create page with namespace conflict
    When user "alice" creates page "firstpage" in "mywiki"
    Then user "alice" doesn't see page "firstpage (1)" in "mywiki"
    And user "alice" sees page "firstpage (2)" in "mywiki"
    And user "alice" doesn't see page "firstpage (3)" in "mywiki"

  Scenario: Create another page with namespace conflict
    When user "jane" creates page "firstpage" in "mywiki"
    Then user "jane" sees page "firstpage (3)" in "mywiki"

  Scenario: Delete a page
    When user "alice" deletes page "firstpage" in "mywiki"
    And user "jane" deletes page "secondpage" in "mywiki"
    Then user "alice" doesn't see page "firstpage" in "mywiki"
    And user "jane" doesn't see page "secondpage" in "mywiki"

  Scenario: Delete wiki with all remaining pages
    Then user "jane" deletes wiki "mywiki"
