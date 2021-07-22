# Changelog

## 0.11.21-beta1 - 2021.07.22

### ✨New
* ♻️lUpdate page list when it changes on the server.

### 🏗️Fixes
* 👤 Show avatar of person who created the collective.
* ✅ Display todo items with checkbox in preview.

### 🌎Translations
* 🗣️ Initial Sinhala translation thanks to HelaBasa

### 🚧Updates & Tooling
* 😎 Check if versions match before building new release.

## 0.10.21 - 2021.07.19

### ✨New

* 🖨️ Print an entire collective or create a pdf from it.
* 🧷 Drag page entry into the current page to create a link.
* 📋 Toggle to list all subpages below the current page.
* 📂 Button to show the current page in files.

### 🏗️Fixes

* 💽 Synchronizing collectives to the desktop app.
* 🔎 Find pages by `fileId` if they cannot be found by path.
* 🧽 Update title when other people rename current page.
* ⛳ Use urls with fileId in the pages list.
* 🚀 Faster loading of absolute links within the collectives app.
* #️⃣ Handling of special chars in collective and page names.

### 🌎Translations
* 🗣️ Complete Czech translation thanks to Pavel Borecki

### 🚧Updates & Tooling
* ✅ Fix test runs in forks.
* ✂️ Only run tests relevant for the changed files.
* 🔌 Update all js dependencies that we can.

## 0.9.21 - 2021.07.06

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

### 🚧Updates & Tooling
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
