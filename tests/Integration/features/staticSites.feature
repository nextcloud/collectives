# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

Feature: staticSites

  Scenario: Publish a collective as website
    When user "jane" creates collective "BehatStaticSiteCollective"
    And user "jane" publishes collective "BehatStaticSiteCollective" as website with slug "behat-static-site"
    Then user "jane" sees website "behat-static-site" in collective "BehatStaticSiteCollective"
    # TODO: Download the archive and check its content once it's provided via public API

  Scenario: Fail to publish a website with a slug in use
    Then user "jane" fails to publish collective "BehatStaticSiteCollective" as website with slug "behat-static-site" with status 400

  Scenario: Fail to publish a website with an invalid slug
    Then user "jane" fails to publish collective "BehatStaticSiteCollective" as website with slug "Invalid Slug" with status 400

  Scenario: Republish a website with a new title
    When user "jane" republishes website "behat-static-site" of collective "BehatStaticSiteCollective" with title "New website title"

  Scenario: Delete a website
    When user "jane" deletes website "behat-static-site" of collective "BehatStaticSiteCollective"
    Then user "jane" doesn't see website "behat-static-site" in collective "BehatStaticSiteCollective"

  Scenario: Trash and delete collective
    Then user "jane" trashes and deletes collective "BehatStaticSiteCollective"
