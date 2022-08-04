# Changelog

## 1.4.0 - 2022.08.04

### ✨New
* 🔎 Indexed full-text search, replacing the former inefficient search.

### 🐛Fixes
* 🔗 Fix links to pages in public share view. (#329, #337)
* 😀 Remove emoji outline below page emojis. (#194)
* 🤸 Several accessibility improvements.
* ➖ Remove duplicate button to toggle app details.
* 🚀 Fix occ commands when Circles app is disabled.

### 🌎Translations
* 🗣️ Czech translation updated thanks to Pavel Borecki.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ German translations updated thanks to Joachim Sokolowski.
* 🗣️ French translation updated thanks to Nathan.
* 🗣️ Russian translation updated thanks to Andrey Rozentals.

### 🚧Updates & Tooling
* 🔌 Remove unnecessary NPM dependencies. Thanks to Ferdinand Thiessen.
* 🔌 Update composer dependencies.


## 1.3.0 - 2022.07.11

### ✨New
* 📋 Major refactoring and improvements of page list.
* 😀 Page list: Emojis for pages (#194).
* 🎚️ Page list: Make resizable.
* 👁️ Page list: Improved icons for pages and collapse/expand badge.
* 🧹 Page list: Remove last edited info. (#344)
* 🔨 Page list: Three-dot menu for actions on a page.
* ℹ️ Page list: Add tooltip showing the full page title if it got truncated.
* 🏛️ Display last edited information in page info bar between title and content.

### 🐛Fixes
* 🔗 Link handling fixes. (#286, #349)
* 💱 Fix error when renaming pages. (#354)
* 🌄 Page list: Make header and landingpage sticky on mobile.
* ✏️ Fix edit/done button on mobile.
* 🧹 Invalidate mountcache when list of collectives changed. (#332)

### 🌎Translations
* 🗣️ French translation updated thanks to Kaiz3r63.
* 🗣️ Portuguese translation added thanks to leonardokr.
* 🗣️ Brasilian-Portuguese translation updated thanks to leonardokr.

### 🚧Updates & Tooling
* ✅ Cypress test for link handling.


## 1.2.1 - 2022.06.20

### 🐛Fixes
* 🔝 Fix persistent user-selected page order for collectives.
* 🖱️ Harmonize bottom padding in edit and read mode to improve autoscroll.

### 🌎Translations
* 🗣️ Don't use fuzzy matching for translation strings.
* 🗣️ Czech translation updated thanks to Pavel Borecki.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ German translations updated.


## 1.2.0 - 2022.06.17

### ✨New
* 🔝 Make user-selected page order for collectives persistent. (#342)

### 🐛Fixes
* 🖼️ Fix displaying images in read mode with latest Nextcloud 24. (#336)
* 🔗 Open non-empty list of collectives on app start on mobile. (#341)
* 🔲 Remove textarea border around editor. (#345)
* 🖱️ Scroll page content when scrolling whitespace on the side. (#338)
* 📝 Further improvements to the view/edit switch.
* 🆕 Focus editor when opening an empty page. (#325)
* 🧹 Delete cruft collectives by listening to CircleDestroyed event. (#326, #327)
* 📄 Always load latest page content when switching to read mode.
* 📜 Improve accessibility of version and backlinks lists.
* 🔻 Shrink app package by about 1MB.
* 🖨️ Expose "Export or print" function in collective actions.
* 🚫 Prevent creating an empty collective. (#331)
* ⚠️ Several improvements to backend exception handling and logging.
* 🐎 Improved performance when fetching Collectives, thanks to Claus-Justus Heine.

### 🌎Translations
* 🗣️ Czech translation updated thanks to Pavel Borecki.
* 🗣️ Brasilian-Portuguese translation updated thanks to Alexandre Lopes.
* 🗣️ French translation updated thanks to Nathan.
* 🗣️ German translations updated thanks to Joachim Sokolowski.

### 🚧Updates & Tooling
* 🔌 Update @nextcloud/text to 25.0.0-alpha.3.
* 🔌 Update all possible javascript dependencies.
* ☝️ Migrate icons from SCSS mixins to Vue components.
* 🖼️ Update Collectives app screenshots.


## 1.1.0 - 2022.05.04

### ✨New
* 🖨️ Completely rework print collective functionality.
* 🌄 Rework view mode layout, sticky title bar. (#291)
* 📝 Retain scroll position when switching between view and edit mode.
* 🖱️ Always scroll active page into viewport in pagelist.

### 🐛Fixes
* 🖼️ Render images in view mode. (#296)
* 📶 Fix alignment of document status message. (#315)
* ❌ Don't expose image delete button in view mode.
* 🖱️ Remove superfluous horizontal scrollbar in view mode. (#320)
* 📜 Fix view of older page versions.
* 🆕 Fix an error when loading editor for existing empty pages.
* 📝 Fix layout of sticky landing page list. (#324)

### 🌎Translations
* 🗣️ Swedish translation added thanks to Simon N.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ French translation updated thanks to Nathan.
* 🗣️ Czech translation updaed thanks to Pavel Borecki.
* 🗣️ German translation updated thanks to Joachim Sokolowski.

### 🚧Updates & Tooling
* 🔌 Update @nextcloud/text to 0.1.0.
* 🔌 Various improvements to our tests.


## 1.1.0-beta2 - 2022.04.14

### 🐛Fixes
* 🔗 Fix opening links with latest @nextcloud/text


## 1.1.0-beta1 - 2022.04.14

### ✨New
* 🔢 Display tables (can be created with Nextcloud 24)
* ⚠️ Display callouts (can be created with Nextcloud 24)

### 🐛Fixes
* 👓 Open file links in viewer directly from the page view.

### 🚧Updates & Tooling
* 🔌 Use the new @nextcloud/text package instead of our custom preview


## 1.0.0 - 2022.04.12

### ✨New
* 📝 Link shares can be editable now.
* 🔎 Add a search field to filter the page list. (#302)
* 🥟 Improve page list UX via sticky header bar and landing page. (#305)
* ⬆️ Add support for PHP 8.1.

### 🐛Fixes
* 📋 Fix styling of collapse/expand badge in page list. (#306)
* 📜 Scroll selected page into view after sorting page list. (#309)
* 💄 Minor fixes when viewing a shared collective.
* 👽 Fix mountpoint setup with mountpoint improvements in Nextcloud 24.

### 🚧Updates & Tooling
* 🔌 Update all possible javascript dependencies.
* 📋 Use modern browser clipboard interface.
* ♻️ Refactorings in the backend code.

### 🌎Translations
* 🗣️ Dutch translation added thanks to Jeroen Bos.
* 🗣️ French translation updated thanks to Nathan.
* 🗣️ Czech translation updaed thanks to Pavel Borecki.


## 0.22.22 - 2022.03.06

### 🏗️Fixes
- ✂️  Fix migration when upgrading to 0.20.22 or newer (#294)


## 0.21.22 - 2022.03.03

### ✨New
* ✨ Support Nextcloud Version 24.

### 🏗️Fixes
- ✂️  Fix migration when upgrading from 0.20.22 to 0.21.22 (#293)

## 0.20.22 - 2022.03.03

### ✨New
* 🛂 Permission management for collectives. (#191)
* 🔝 Allow to configure default page order for collectives. (#273)
* 🙂 Assign a random emoji to new collectives. (#281)

### 🏗️Fixes
* ⚙️ Several improvements to the collective settings.
* 📝 Don't load editor in view mode.
* 🚚 Improve database initialization on initial installation.
* 🧹 Mark background jobs as time insensitive.

### 🌎Translations
* 🗣️ Chinese translation added thanks to Wang Jiaxiang.
* 🗣️ French translation updated thanks to Nathan.
* 🗣️ Czech translation updaed thanks to Pavel Borecki.
* 🗣️ German translation updated thanks to Joachim Sokolowski.

### 🚧Updates & Tooling
* 🔌 Update all possible javascript dependencies.
* 🔌 Update all possible PHP dependencies.


## 0.19.22 - 2022.02.08

### ✨New
* ⚙️ Settings for a collective moved into a modal (#258)
* 💱 Collectives can be renamed from their settings (#162)
* 🙂 The emoji of a collective can be changed in its settings (#257)

### 🏗️Fixes
* 🏛️ Fix argument type for folders in VersionsBackend

### 🌎Translations
* 🗣️ Czech translation updaed thanks to Pavel Borecki.
* 🗣️ French translation updated thanks to Nathan.
* 🗣️ German translation updated thanks to Joachim Sokolowski.
* 🗣️ Japanese translation updated thanks to あわしろいくや.


## 0.18.22 - 2022.01.12

### ✨New
* 📂 Allow to configure folder for collectives per user (#275).

### 🏗️Fixes
* ▶️ Only initialize collectives folder if user has collectives (#238).
* 🌄 Various minor UI tweaks. Thanks to ya-d for suggestions (#271).
* ✏️ Improve edit button experience while loading editor (#268).
* 🔗 Open external links in new window.
* 🗣️ Fixed usage of german formal translation (#227).
* 📄 Ellipsise long page titles (#255).
* ⚡️ Numerous fixes to the storage backend implementation.
* 🔎 Improve unified search experience (#277).

### 🌎Translations
* 🗣️ Slovenian translation added thanks to Matej U.
* 🗣️ Spanisch translation added thanks to larusalka.
* 🗣️ German translation updated thanks to Joachim Sokolowski.


## 0.17.22 - 2021.12.16

### 🏗️Fixes
* 🧹 Ignore empty share tokens in ShareDeletedListener (#265).

### 🌎Translations
* 🗣️ Tamil translation started thanks to Rajasekaran Karunanithi.
* 🗣️ Updated Czech translation thanks to Pavel Borecki.
* 🗣️ Japanese translation started thanks to あわしろいくや.


## 0.16.22 - 2021.11.29

### ✨New
* 🎁 Link to share an entire collective (#199).
* 🎯 Page actions menu next to edit/done button (#256).
* ✨ Support Nextcloud Version 23.

### 🏗️Fixes
* 🔓 Return correct privilege level for trashed collectives (#260).
* 🚨 Fix rendering of error messages.

### 🚧Tooling
* ⛓️ More robust tests (#263).
* 🛰️ Change API routes from `/_collectives` to `/_api`.

### 🌎Translations
* 🗣️ Italian translation updated thanks to Marco Trevisan.
* 🗣️ Brasilian-Portuguese translation updated thanks to Leonardo Colman.
* 🗣️ French translation updated thanks to Nathan.


## 0.15.22 - 2021.10.20

### ✨New
* ✨ Occ command to create new collectives.

### 🏗️Fixes
* 🧹 Fix ExpirePageVersions background job (#247).
* 🔎 Fix broken links from search results (#249).

### 🌎Translations
* 🗣️ Italian translation started thanks to Marco Trevisan.
* 🗣️ Catalan translation completed thanks to Jordán.
* 🗣️ Portuguese (Brazil) translation started thanks to Leonardo Colman.
* 🗣️ French translation updated thanks to Nathan.


## 0.14.22 - 2021.09.02

### 🏗️Fixes
* 🧷 Only display collective if user is in circle (#230).

### 🚧Tooling
* 🏷️ Handle existing tags better in Makefile.


## 0.13.22 - 2021.08.24

### 🏗️Fixes
* 💽 Do not try to create collectives folder if quota is 0 (#229).

### 🌎Translations
* 🗣️ Updated Czech translation thanks to Pavel Borecki.


## 0.12.22 - 2021.08.16

### ✨New
* 🔗 Show backlinks to a page (#220).
* 🎩 Allow circle admins to create, update and delete collectives (#217).

### 🏗️Fixes
* 📜 Highlight the landing page when it's active (#215).
* 🧹 Clear page list when switching the collective (#221).
* ♻️  Improvements to page list loading indicator.
* 📝 Don't reload editor on page updates in preview mode (#222).

### 🌎Translations
* 🗣️ Initial German (formal) translation thanks to Joachim Sokolowski.
* 🗣️ Updated Czech translation thanks to Pavel Borecki.
* 🗣️ Updated French translation thanks to Nathan.

### 🚧Updates & Tooling
* ✅ Improvements on CI testing.
* 🔌 Update all js dependencies that we can.


## 0.11.22 - 2021.08.02

### ✨New
* 📝 Page templates (#66).
* ♻️ Update page list when it changes on the server (#50).
* 🙂 Allow to change emoji of collectives (#210).

### 🏗️Fixes
* 📂 Ignore folders without markdown files in page tree (#171).
* 👤 Show avatar of person who created the collective (#197).
* ✅ Display todo items with checkbox in preview (#178).
* 📜 Highlight active page in page list (#208).
* 🔝 Highlight selected page order (#205).
* 🔌 Add files_versions to list of required apps (#193).
* 🖼️ Display animated gifs and webp graphics (#202).
* 👥 Show member management link only to admins (#212).
* 🧽 Update document title when changing collective (#211).
* 🔂 Don't repeat page content after updating the page list (#214).
* 💱 Allow to rename pages without explicit save (#206).
* 🌀 Remove spinning wheel in page list.

### 🌎Translations
* 🗣️ Initial Sinhala translation thanks to HelaBasa.
* 🗣️ Updated Czech translation thanks to Pavel Borecki.

### 🚧Updates & Tooling
* 😎 Check if versions match before building new release.
* 📋 Screenshots and documentation updates.


## 0.10.22 - 2021.07.19

### ✨New

* 🖨️ Print an entire collective or create a pdf from it.
* 🧷 Drag page entry into the current page to create a link.
* 📋 Toggle to list all subpages below the current page.
* 📂 Button to show the current page in files.
* 👥 Direct link to the circle for membership management.

### 🏗️Fixes

* 💽 Synchronizing collectives to the desktop app.
* 🔎 Find pages by `fileId` if they cannot be found by path.
* 🧽 Update title when other people rename current page.
* ⛳ Use urls with fileId in the pages list.
* 🚀 Faster loading of absolute links within the collectives app.
* 💯 Handling of special chars in collective and page names.

### 🌎Translations
* 🗣️ Complete Czech translation thanks to Pavel Borecki.

### 🚧Updates & Tooling
* ✅ Fix test runs in forks.
* ✂️ Only run tests relevant for the changed files.
* 🔌 Update all js dependencies that we can.


## 0.9.22 - 2021.07.06

### ✨New
* 🖨️ Bring proper formatting to print pages.

### 🌎Translations
* 🗣️ Updated Russion translation thanks to Artem.
* 🗣️ Updated French translation thanks to Nathan.

### 🏗️Fixes
* 👁️ Display app icon for collectives without emoji.
* 🏛️ Fix view of an old page version, improve UI.

### 🚧Updates
* 🔌 Update all dependencies and migrate to Node.js 14.
* 🔌 Migrate to Circles API for Nextcloud 22.


## 0.8.21 - 2021.06.18

### ✨New
* 👁️ Use SVG Icons for single page and page with subpages.
* 🔎 Meaningful collective not found and page not found messages.
* ✨ Improved startpage with button to create a collective.
* 🛡️ Save text and title with a single button.
* 📝 Update list of pages every 60 seconds.

### 🌎Translations
* 🗣️ Initial French translation thanks to shiromarieke and Nathan Bonnemains.
* 🗣️ Updated Norwegian translation thanks to Allan Nordhøy.

### 🏗️Fixes
* 💽 MountProvider works with Nextcloud 22.
* 🧷 Use correct links in page list after rename of parent page.
* 💱 Rename parent pages properly - do not create another subpage.
* ✅ Browser error due to duplicate subscription of an event handler.
- 🚀 Make sure `occ` works even if circles app is missing.
* ✅ Work around some bugs in the CI so all tests pass.

### 🚧Updates & Tooling
* 🔌 Migrate to Circles API for Nextcloud 22.
* 👷 Refactor to ease support for Nextcloud 22.
* 🗑️ Make task to remove releases.


## 0.7.0 - 2021.05.31

### ✨New
* 🔎 Fulltext search support in app context.
* 🔝 Sort pages by activity or alphabetically.
* 📋 Sort collectives alphabetically.
* 💘 Add app navigation order '11'.

### 🌎Translations
* 🗣️ Initial Norwegian translation thanks to Allan Nordhøy.
* 🗣️ Initial Russion translation thanks to Artem.
* 🗣️ Improved German translation thanks to J. Lavoie.

### 🏗️Fixes
* 👓 Allow to expand/collapse subpage lists with keyboard.
* 📛 Fix renaming pages with subpages.
* 📝 Improve page list and editor handling in frontend.
* 🏗️ Various fixes regarding subpages support.

### 🚧Updates
* 👷 Refactored Makefile.


## 0.6.2 - 2021-05-20

### Fixes
- 📝 Loading the text editor on some instances.

### Tests
- ✅ Make a flaky test pass more reliably.


## 0.6.1 - 2021-05-20

### Fixes
- 🗑️ Deleting pages.
- 🌐 Creating Links between Pages.
- 🧷 Opening Links in the page preview.


## 0.6.0 - 2021-05-19

### ✨New
- 📝 Create subpages to organize your shared knowledge.
- 🏛️ Restructured page sidebar and version history.
- 🌄 Many small user interface improvements.

### Fixes
- 🧱 Don't break OCC commandline when Circles app is missing.
- ⭐ Use app icon as placeholder for collectives without emojis.
- 🗑️ Don't display empty list of deleted collectives.
- 👷 Huge code refactoring under the hood.
- 🔎 Use app icon for collectives-related search results.

### Updates
- 🔌 Update all dependencies.


## 0.5.1 - 2021-04-26

### Fixes
- ✂️  Fix migration step to split emojis from collective names.


## 0.5.0 - 2021-04-26

### ✨New
- ⭕ Pick an existing circle when creating a new collective.
- 🗑️ Dialog to decide if the circle should be deleted when deleting a collective.
- 📋 Cleaner pages list, including info for the start page.
- 📱 Better workflows for mobile use.

### Fixes
- ✂️  Split emojis from collective names.
- 🧽 Always use sanitized filename for mountpoint.
- 📲 Fix overlapping elements in mobile view.
- 💽 Fix database query in ExpirePageVersions background job.
- ⏱️ Show loading spinner when list of collectives isn't available yet.

### Updates
- 🔌 Update all dependencies.


## 0.4.0 - 2021-04-19

### ✨ New
- ♻️l Restore deleted collectives.
- 🗑️ Permanently remove deleted collectives.
- 💱 Rename an existing collective.
- ⭕ Create a collective for an existing circle.

### Fixed
- 🌐 Loading Collectives and Pages via Links.
- 🏖️ Links from the Collective Startpage.

### Upgrades
- 🚀 Update all npm dependencies.


## 0.3.1 - 2021-04-06

### Fixed
- ✨ Attempting to create collective with same name
     redirects to existing collective
- ℹ️  Have `Info` and `Warning` notices for naming conflices.

### Upgrades
- 🚀 Update all npm dependencies.

### Tests
- ✅ Completely rework the CI setup and make it 2x faster.


## 0.3.0 - 2021-03-23

### ✨ New
- 🖼️ New icon for the Collectives app \o/.
- 📂 In the files app you now find Collectives in a dedicated subfolder.
- 👁️ Collective folders now have their own icon.
- 🗺️ Support for localized start page for new collectives.
- 🗑️ Collectives can finally be deleted (by their owners).
- 📱 Improved mobile experience - you can now see the page content.

### Fixed
- 🚚 Garbage collector for obsolete database entries.
- 3️⃣ Treat digits as non-emoji characters in collective names.
- 🔎 Check if Circles app is installed in SearchProviders.

### Upgrades
- 🚀 Improved support for PHP 8.0 and Nextcloud 21.


## 0.2.3 - 2021-03-10

### Fixed
- 👥 Adding groups to a collective is now supported.

### Upgrades
- 🛡️ Upgrade all vulnerable dependencies.
- ✨ Upgrade all eslint releated development dependencies.
- 🚀 Use new Doctrine class name for Nextcloud 21 compatibility.

### Tests
- ✅ Add initial end-to-end tests for collectives.
- 🔎 Add regression test for missing circles app.


## 0.2.2 - 2020-10-20

### Fixed
- 🧷 Handle missing circles app gracefully.
