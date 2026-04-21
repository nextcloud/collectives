# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: search recent pages

  Scenario: Create collectives and pages for search testing
    When user "jane" creates collective "SearchCollective1"
    And user "jane" creates page "searchpage1" with parentPath "Readme.md" in "SearchCollective1"
    And user "jane" waits for 1 seconds
    And user "jane" creates page "searchpage2" with parentPath "Readme.md" in "SearchCollective1"
    And user "jane" waits for 1 seconds
    And user "jane" creates collective "SearchCollective2"
    And user "jane" waits for 1 seconds
    And user "jane" creates page "searchpage3" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage4" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage5" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage6" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage7" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage8" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage9" with parentPath "Readme.md" in "SearchCollective2"
    And user "jane" creates page "searchpage0" with parentPath "Readme.md" in "SearchCollective2"

  Scenario: Search recent pages from multiple collectives
    When user "jane" searches recent pages with query ""
    Then user "jane" sees 10 search results
    And user "jane" sees page "searchpage0" in search results
    And user "jane" sees page "searchpage9" in search results
    And user "jane" sees page "searchpage8" in search results
    Then the first search result is page "searchpage0"

  Scenario: Search recent pages respects limit parameter
    When user "jane" searches recent pages with query "" and limit 2
    Then user "jane" sees 2 search results

  Scenario: Search recent pages by title
    When user "jane" searches recent pages with query "searchpage1"
    Then user "jane" sees 1 search results
    And user "jane" sees page "searchpage1" in search results
    And user "jane" doesn't see page "searchpage2" in search results

  Scenario: Search is case-insensitive
    When user "jane" searches recent pages with query "SEARCHPAGE1"
    Then user "jane" sees 1 search results
    And user "jane" sees page "searchpage1" in search results

  Scenario: Search with no results
    When user "jane" searches recent pages with query "nonexistent"
    Then user "jane" sees 0 search results

  Scenario: Search with partial match
    When user "jane" searches recent pages with query "page"
    Then user "jane" sees 10 search results
    And user "jane" sees page "searchpage0" in search results
    And user "jane" sees page "searchpage9" in search results
    And user "jane" sees page "searchpage8" in search results

  Scenario: Search results include collective name
    When user "jane" searches recent pages with query "searchpage1"
    Then user "jane" sees page "searchpage1" with collectiveName "SearchCollective1" in search results

  Scenario: Search across multiple collectives shows different collective names
    When user "jane" searches recent pages with query "searchpage"
    Then user "jane" sees page "searchpage1" with collectiveName "SearchCollective1" in search results
    And user "jane" sees page "searchpage2" with collectiveName "SearchCollective1" in search results
    And user "jane" sees page "searchpage3" with collectiveName "SearchCollective2" in search results

  Scenario: Search with SQL wildcard underscore is escaped
    When user "jane" creates page "test_special" with parentPath "Readme.md" in "SearchCollective1"
    And user "jane" waits for 1 seconds
    And user "jane" creates page "testXspecial" with parentPath "Readme.md" in "SearchCollective1"
    And user "jane" searches recent pages with query "test_"
    Then user "jane" sees 1 search results
    And user "jane" sees page "test_special" in search results
    And user "jane" doesn't see page "testXspecial" in search results

  Scenario: Search with SQL wildcard percent is escaped
    When user "jane" creates page "test%page" with parentPath "Readme.md" in "SearchCollective1"
    And user "jane" searches recent pages with query "test%"
    Then user "jane" sees 1 search results
    And user "jane" sees page "test%page" in search results

  Scenario: Search respects user permissions
    When user "alice" searches recent pages with query "searchpage1"
    Then user "alice" sees 0 search results
    When user "alice" joins team "SearchCollective1" with owner "jane"
    And user "alice" searches recent pages with query "searchpage1"
    Then user "alice" sees 1 search results
    And user "alice" sees page "searchpage1" in search results
    And user "alice" doesn't see page "searchpage3" in search results

  Scenario: Search for finds index page
    When user "jane" creates page "ProjectFolder" with parentPath "Readme.md" in "SearchCollective1"
    And user "jane" waits for 1 seconds
    And user "jane" creates page "subpage" with parentPath "ProjectFolder.md" in "SearchCollective1"
    And user "jane" searches recent pages with query "ProjectFolder"
    Then user "jane" sees 1 search results
    And user "jane" sees page "ProjectFolder" in search results

  Scenario: Partial search finds index page
    When user "jane" searches recent pages with query "Project"
    Then user "jane" sees page "ProjectFolder" in search results

  Scenario: Search for nested subpage name finds index page
    When user "jane" creates page "NestedFolder" with parentPath "ProjectFolder/Readme.md" in "SearchCollective1"
    And user "jane" waits for 1 seconds
    And user "jane" creates page "deeppage" with parentPath "ProjectFolder/NestedFolder.md" in "SearchCollective1"
    And user "jane" searches recent pages with query "NestedFolder"
    Then user "jane" sees 1 search results
    And user "jane" sees page "NestedFolder" in search results

  Scenario: Searching for Readme does not return index pages
    When user "jane" searches recent pages with query "Readme"
    Then user "jane" sees 0 search results

  Scenario: Clean up
    Then user "jane" trashes and deletes collective "SearchCollective1"
    And user "jane" trashes and deletes collective "SearchCollective2"
