Feature: collectivePages

  Scenario: Create collective and first page
    When user "jane" creates collective "mycollective"
    And user "jane" creates page "firstpage" in "mycollective"
    Then user "jane" sees page "firstpage" in "mycollective"

  Scenario: Share collective (with pages) and create second page
    When user "alice" is member of circle "mycollective" with admin "jane"
    And user "alice" creates page "secondpage" in "mycollective"
    Then user "alice" sees page "secondpage" in "mycollective"
    And user "jane" sees page "secondpage" in "mycollective"

  Scenario: Touch page
    When user "alice" touches page "firstpage" in "mycollective"
    Then user "alice" last edited page "firstpage" in "mycollective"

  Scenario: Create page with namespace conflict
    When user "alice" creates page "firstpage" in "mycollective"
    Then user "alice" doesn't see page "firstpage (1)" in "mycollective"
    And user "alice" sees page "firstpage (2)" in "mycollective"
    And user "alice" doesn't see page "firstpage (3)" in "mycollective"

  Scenario: Create another page with namespace conflict
    When user "jane" creates page "firstpage" in "mycollective"
    Then user "jane" sees page "firstpage (3)" in "mycollective"

  Scenario: Rename page
    When user "jane" renames page "firstpage (2)" to "thirdpage" in "mycollective"
    Then user "jane" sees page "thirdpage" in "mycollective"
    And user "jane" doesn't see page "firstpage (2)" in "mycollective"

  Scenario: Delete a page
    When user "alice" deletes page "firstpage" in "mycollective"
    And user "jane" deletes page "secondpage" in "mycollective"
    Then user "alice" doesn't see page "firstpage" in "mycollective"
    And user "jane" doesn't see page "secondpage" in "mycollective"

  Scenario: Trash collective with all remaining pages
    Then user "jane" trashes collective "mycollective"

  Scenario: Delete collective+circle with all remaining pages
    Then user "jane" deletes collective+circle "mycollective"
