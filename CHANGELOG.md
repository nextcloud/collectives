# Changelog

## 3.3.0 - 2025.11.03

### ✨New
* 👷‍♀️ Add service worker that caches app assets for offline support. (#1772)
* 📎 List number of attachments and link to sidebar from page info bar. (#346)

### 🐛Fixes
* 🔒 Fix validating password hash in public shares in Nextcloud 33.
* 📁 Remove obsolete mimetype folder icon registration.
* 🔎 Fix search in member picker when pasting into search field.
* ⌛ Update last edited timestamp in page info bar.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.2.4 - 2025.10.26

### 🐛Fixes
* 🐛 Fix race condition with loading CollectiveExtraAction.


## 3.2.3 - 2025.10.23

### 🐛Fixes
* 🐛 Fix page actions being disabled for subpages starting in second level.


## 3.2.2 - 2025.10.23

### 🐛Fixes
* 👥 Improve UX around sharing and managing members. Thanks @silverkszlo (#2003)
* 🐛 Detect landing page if it has a slug.
* 💄 Improve missing app dependencies error message. Thanks @janbaum (#1700)
* 💄 Better error message if collective name is too long. Thanks @cyan-1 (#1833)
* 🔌 Don't fetch collectives when offline.
* 🔌 Disable more features in UI when offline.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.2.1 - 2025.10.13

### 🐛Fixes
* 🐛 Separate files for CSS entry points.
* 💄 Show similar options page actions menu of page list and page title.
* 💄 Don't use a focus trap for the additional page tags popover.
* 🐛 Fix broken session request in public shares.
* 🐛 Save `deletedBy` for trashed pages.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.2.0 - 2025.10.07

### ✨New
* 🔌 Add offline state indicator.
* 🔌 Disable some features in UI when offline.

### 🐛Fixes
* 💬 Clarify undo popup after leaving collective. (#1856)
* 💄 Switch to more outline icons to follow Nextcloud 32 style.
* 💄 Use filled star icon for favorites in page list.
* 🐛 Disable drag and drop for page favorites.
* 🐛 Sort favorite pages according to page list.
* 🐛 Don't escape collective name in title. (#1690)
* 🐛 Don't set current page as loading when moving another page.
* 🏷️ Make tag filtering and sorting case-insensitive.
* 🐛 Don't show a toast when no tags got deleted.
* 🐛 Fix arrow buttons visual feedback in move/copy modal.
* 💄 Fix color of remove button in members modal.
* 💄 Make "Manage members" button tertiary.
* 💄 Don't override text color for active page version element.
* 🔌 Don't send API requests when offline.
* 🐛 Always delete editor, fix race condition with dangling editor.
* 🐛 Avoid loading state flipflop when first opening a page.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⚙️ Migrate from webpack to vite.
* ⚙️ Migrate to eslint 9.
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.1.2 - 2025.09.01

### 🚧Updates & Tooling
* ⬆️ Bump guzzlehttp/guzzle from 7.9.3 to 7.10.0. (#1942)
* ⬆️ Bump codecov/codecov-action from 5.4.3 to 5.5.0. (#1943)
* ⬆️ Update dependency jest to ^30.1.1. (#1947)


## 3.1.1 - 2025.08.18

### 🐛Fixes
* 💄 Switch to outline icons to follow Nextcloud 32 style.
* ⬇️ Add action to download markdown file. (#1347)
* 💄 Change editor width to 80ch.
* 💄 Open collective automatically if only one exists. (#1924)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.1.0 - 2025.08.11

### ✨New
* 🏷️ Page tags. (#470, #1604, #1703)
* 🚀 Send live page list updates directly via notify_push deamon.

### 🐛Fixes
* 🐛 Stop slugs migration early if it already ran.
* ⌛ Raise session valid time to fix outdated active sessions.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.0.3 - 2025.07.28

### 🐛Fixes
* 📎 Load attachments in view mode. Thanks @emberfiend. (#1885)
* 📎 Fix opening attachments in viewer from sidebar in public shares.
* 🐛 Don't fetch templates in page shares.
* ♻️ Avoid redundant navigation for URLs with hash.
* 🐛 Fix displaying PageInfoBar with Nextcloud 32.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 3.0.2 - 2025.07.17

### 🐛Fixes
* Make sure slugs are generated upon app upgrades. (#1879)
* Handle page slug routes without collective slug. (#1879)
* Use sluggified URLs in dashboard links.
* Fix page titles in recent pages widget.
* Redirect to sluggified URL when browsing old URL format.


## 3.0.1 - 2025.07.14

### 🐛Fixes
* 🪤 Catch more circles errors when generating slugs.


## 3.0.0 - 2025.07.14

### ✨New
* 🏬 Persist collectives and pages metadata in browser local storage.
* 🌐 Slugified URLs to collectives and pages. Thanks @Koc.
* 🔥 Documented OCS API with OpenAPI specification. (#690)
* 🧹 Remove Nextcloud 29 and PHP 8.0 support.

### 🐛Fixes
* 🧹 Remove debugYjs function.
* 🔥 Only initialize a collective session as logged in user.
* 🩹 Add getRevision function required by NC 32.
* 🔗 Pass query params to axios using the params object.
* 🔗 Fix URL to delete a session.
* ⚙️  Use isNull and emptyString in slug queries.
* 🚢 Prereleases don't need the nightly flag in app store.
* 🔥 Fix URL to delete a collectives session.
* 🪤 Catch missing circles when generating slugs.
* 🧹 Remove unused linkHandler mixin.
* 🔔 Fix tracking notify_push sessions.
* 🖱️ Fix dropping page below last item in list. (#423)
* 💄 Align "page not found" empty content vertically.
* 💄 Polish hover effect on recent pages. Thanks @kra-mo.
* 🐛 Fix ExpirePageVersions background job. (#1834)
* 🔗 Fix default route in recent pages widget. (#1830)
* 🔎 Show page path in page search results. (#1778)
* 🔗 Resolve links to landing pages in page reference provider. (#701)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.18.0 - 2025.06.09

### ✨New
* 📜 Improved version support with named versions and authors. (#234)
* 🚀 Faster loading when switching between collectives back and forth.
* 🔃 Allow to sort pages descending by title/time. (#1680)
* 🔎 Full text search in public shares. (#1790)

### 🐛Fixes
* 💄 Several smaller fixes around new templates management. (#1760)
* 💄 Show title and emoji of landingpage in recent pages. (#1761)
* 🐛 Fix listing members with latest server releases.
* 💄 Ellipsise overflowing timestamp strings in recent pages. (#1786)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.17.1 - 2025.05.16

### 🐛Fixes
* Fix migration of templates via web updater. (#1763)


## 2.17.0 - 2025.05.14

### ✨New
* 📝 New templates management. (#267, #1195)
* 🛬 Overhauled landingpage widgets.
* 🧹 Remove Nextcloud 27 + 28 support.
* 🔔 Custom notifications for mentions in Nextcloud 31+. (#1469)

### 🐛Fixes
* 💄 Save title on submit when in view mode.
* 💄 Make toolbar sticky to bottom on mobile.
* 💄 Several design papercut fixes.
* 🧹 Unset trash pages when switching the collective.
* 🐛 Don't throw when adding subpage of page without metadata. (#1726)
* 🧹 Hide recent pages widget if less than four pages.
* 🧹 Hide team overview button if only one member.
* 🖱️ Scroll to heading when opening anchor link to page. (#1736)
* 💄 Fix jumping page order when adding a new page.
* 🔃 Fix broken page list order after adding subpage. (#1360)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.16.3 - 2025.04.24

### 🐛Fixes
* 🖱️ Fix expanding/collapsing pages with subpages in the page list.


## 2.16.2 - 2025.04.23

### 🐛Fixes
* 💄 Fix indention in page list.
* 💄 Fix jumping page order when adding a new page.
* 💄 Replace editor heading in landingpage with a thin line.
* 💄 Fix sliding button style in recent pages widget.
* 💄 Don't show team overview button in public shares. Thanks @tintou.
* 🚀 Improve performance in collectives list request.
* 🚀 Improve performance of dashboard request. Thanks @Koc.
* 🖱️ Scroll favored page in page list into viewport (#1673)
* 🖱️ Fix scroll container for page content. (#1740)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.16.1 - 2025.02.13

### 🐛Fixes
* 🚧build: exclude all hidden files and folders

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.

## 2.16.0 - 2025.01.27

### ✨New
* ✨ Add Nextcloud 31 support

### 🐛Fixes
* ✅ Adjust tests to Nextcloud 31 login changes
* 💽 Make collectives work with sharding

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.15.2 - 2024.12.18

### 🐛Fixes
* 🚀 Performance: use `probeCircles()` instead of `getCircles()`. (#498)
* 📱 Fix positioning of outline. (#1614)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.15.1 - 2024.11.20

### 🐛Fixes
* 💄 Landing page widgets and page content in scroll container. (#1578)
* 📱 Fix maximum editor/page width on mobile. (#1577)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.15.0 - 2024.10.29

### ✨New
* ⭐ Add support for page favorites. (#300)
* 🔧 Add console function to debug sync/Yjs issues.

### 🐛Fixes
* 🖨️ Several print style fixes. (#1110)
* 💄 Make page list header, landing page and trash sticky again. (#1523)
* ⌛ Update timestamp in page info bar when page gets saves via "Done" button. (#1371)
* 💄 Fix alignment of landing page widgets.
* 🐎 Use storage id for performant index usage on dashboard query.
* 🏛️ Add return types of storage wrapper.
* 👷 Exclude rector.php from release tarball. (#1541)
* 🔗 Fix page link in reference widgets for index pages.
* 🔗 Preserve anchors in links of reference widgets. Thanks @Koc.
* 🐛 Create index page if subfolders contain pages.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.14.4 - 2024.09.24

### 🐛Fixes
* 🛡️ Give public shares always permission of a simple user.
* 💄 Use flexbox to align editor container and search dialog.
* 🐘 Adjust PHP CollectiveStorage class to be compatible with Nextcloud 31.
* 💄 Use dark SVG as widget icon. Thanks @Akhil. (#1475)
* 👷 Run distclean as dependency of release in Makefile. (#1482)
* 💄 Add bottom border to menubar.
* 👷 Migrate development tools to vendor-bin.
* 🔎 Add icon to clear the filter string. (#1501)
* 🖱️ Close actions menu when scrolling members.
* 🐛 Ignore folders if parent folder has no index page. (#1494)
* 🙍 Use display name of own user instead of user ID. (#1504)
* 🐛 Create landing page if missing. (#943)
* 📂 Allow to mount the collectives user folder into a subfolder. (#514)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.14.3 - 2024.09.10

### 🐛Fixes
* 📝 Page content: ensure consistent state between view and edit mode. (#1437)
* 🔎 Full text search index: Don't choke on missing files (#1448)
* ♻️ Fix error on renaming a collective. (#1456)
* 🎯 Page list: sort numbers numerically. (#1447)
* 🎯 Page list: Calculate scroller height dynamically for filtered view. (#1339)
* 🔗 Backlinks: detect links with title in markdown syntax. (#1451)
* 📱 Navigation: show details when switching collective on mobile. (#1233)
* 💄 Improve design of missing app error box. (#1330)
* 🔎 Several fixes to search highlighting. (#1461)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.14.2 - 2024.08.23

### 🐛Fixes
* 🐛 Fix error "Could not fetch page trash" due to call on null. (#1435)
* 🖱️ Fix moving page by drag and drop. (#1436)


## 2.14.1 - 2024.08.21

### 🐛Fixes
* 💄 Refactor "New collectives" button, make it HTML spec compatible. (#1382)
* 💄 Bring back top padding for titlebar on Nextcloud < 30. (#1426)
* 🐛 Fix error "Could not fetch page trash" due to call on null. (#1431)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.


## 2.14.0 - 2024.08.19

### ✨New
* 📝 Persist full page width setting in database. Thanks @Koc.
* 🔒 Add rate limits to public page controller functions.

### 🐛Fixes
* 🍍 Migrate frontend vue store from vuex to pinia.
* 💄 Several small UI fixes and improvements.
* 🔒 Fix updating shares with empty password and password policy. (#1327)
* ♻️ Migrate controller annotations to attributes.
* ♻️ Migrate license/copyright headers to SPDX format.
* 🐛 Fix error with v-click-outside in collectives trash.
* 🔗 Fix public share detection in editor.
* 🔎 Use proper editor API for search highlighting.
* 🚀 Performance: only load files script for the files app.
* 🚀 Performance: Optimize getPagesFromFolder function.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.13.0 - 2024.07.25

### ✨New
* ✨ Add Nextcloud 30 support, remove Nextcloud 26 support.
* 🔎 Highlight search results from page list in page content. (#648)
* 🖼️ Allow to lookup page references in public shares. (#1275)

### 🐛Fixes
* 🔒 Fix creating shares with password policy. (#1269)
* 📌 Allow to toggle recent pages in public shares. (#1192)
* 🧹 Reset filtered page list when switching collectives.
* 🐛 Fix error when loading page references.
* 💬 Separate message and details with a space in error toast messages.
* 🐛 Don't show an error if the page trash is disabled.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.12.0 - 2024.06.11

### ✨New
* 🔎 Add content search to filter in page list. (#1094)
* 🔔 Track notify_push sessions, notify all active users. (#1118)

### 🐛Fixes
* 🗑️ Always return trashed collectives as simple list. (#1289)
* 🔃 Don't update last edit user when changing sort order. (#1122)
* 📱 Unbreak editing on mobile. (#1232, #1293)
* 🚪 Show 'Leave collective' option conditionally. (Fixes: #1103, #1259)
* 💄 Fix displaying user bubble in view mode.
* 💄 Center empty content in home and notfound view.
* 💄 Fix resizing members widget according to available space.
* 🐛 Fix console errors about debounced methods in different contexts.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.11.0 - 2024.05.16

### ✨New
* 🔒 Allow to protect shares with a password. (#505)
* ✅ Add setup checks for PHP SQLite dependency. Thanks @hweihwang. (#1031)
* 💄 Automatically open collective if only one exists for user. (#1241)

### 🐛Fixes
* 🖱️ Fix sorting in page list using drag and drop. (#1246, #1257)
* 📄 Fix outdated page content in view mode. (#1194)
* 🔎 List subpages when filtering page list. Thanks @grnd-alt. (#1190)
* 📝 Improve documentation about app dependencies. (#1220)
* 🗑️ Make trash backend compatible with Nextcloud 30.
* 🔗 Improve wording for sharing options. Thanks @Jerome-Herbinet. (#1225)
* 💄 Fix layout of delete collective modal. Thanks @elzody. (#1189, #1250)
* 💄 Make sort order pill in page list pixel perfect when scrolling.
* 🐛 Fix JS console error for recent page tiles without last changed user.
* 💄 Use standard `""` quotes in collectives trash modal. (#1190)
* ♿ Completely migrate from v-tooltip to native browser tooltips.
* 📝 Add documentation about using group_everyone app. (#1202)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.10.1 - 2024.03.27

### 🐛Fixes
* 🔎 Improve filtering large page lists. Thanks @grnd-alt. (#1090)
* 🔎 Set default language for TNTSearch. Thanks @Koc.
* 💄 Fix alignments and padding of page list header items.
* 🔗 Fix anchor link scrolling. Thanks @Koc.
* 📎 Fix image and attachment loading in print view. (#1068)
* 🔗 Fix bugs with page reference provider.
* 👥 Link to teams overview from landing page. Thanks @grnd-alt. (#1168)
* 🔗 Fix link shares of subpages. (#1147)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.10.0 - 2024.03.07

### ✨New
* 🔗 Add support public page shares. (#515)
* 🔗 Allow to create several shares for a collective. (#633)
* ✨ Add Nextcloud 29 support.
* 🧹 Remove Nextcloud 25 and PHP 7.4 support.
* 👪 Rename Circles to Teams (change from Nextcloud 29).
* 👪 Add a team resource provider.

### 🐛Fixes
* 💄 Show 'open sidebar' when sidebar is closed.
* 📂 Don't revert subfolders for leaf pages.
* 🔗 Improved link handling for links to collectives pages.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.9.2 - 2023.12.06

### 🐛Fixes
* 📎 Fix several issues with displaying attachments. (#620, #964)
* 🔎 Always search for page content, not only in app realm.
* 📌 Dashboard: respect passed in item limit. Thanks @jld3103.
* 📋 Changelog: Correct styling. Thanks @SimJoSt.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.9.1 - 2023.11.14

### 🐛Fixes
* 🆕 Improve UX when creating a new collective without members.
* 📌 Dashboard: Display parent page path in recent pages widget. (#1010)
* 🔗 Accept `<link>` syntax in parsed markdown when searching for backlinks. (#1007)
* 🐛 Fix dashboard issue for users without a collective.
* 💄 Fix alignment of page heading loading skeleton.
* 📋 Improve wording around user entities: members can be accounts, circles or groups.
* ⌨️ Save document on `<Ctrl>-<S>` when page title is focussed. (#989)
* ℹ️ Don't always show file app info header for guest users. (#893)
* 🧹 Fix `ExpireTrashPages` background job if no trash backend available. (#968)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update PHP composer dependencies.


## 2.9.0 - 2023.11.08

### ✨New
* 🛺 Allow to copy/move pages in and between collectives. (#488)
* 📌 Add recent pages dashboard widget. (#113)
* 👽 Use Text editor via API on Nextcloud 26 and newer. (#18, #932, #969)
* ✨ Add Nextcloud 28 support.

### 🐛Fixes
* 📝 Don't autofocus editor when opening page in edit mode. (#596)
* 🔗 Fix backlink detection with special chars in trusted_domains config. (#330)
* 🧹 Don't show 'Leave collective' action in public shares.
* 🧹 Don't show Nextcloud footer in public shares. (#848, #944)
* 💄 Fix layout of text editor when opened in viewer.
* 🖨️ Several print layout fixes and improvements. (#542, #543)
* 📋 Added documentation on searching. Thanks @pjrobertson.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.8.2 - 2023.10.04

### 🐛Fixes
* 🐛 Migrate settings field in collectives_u_settings DB table to string type. (#917)


## 2.8.1 - 2023.10.02

### 🐛Fixes
* 🐛 Fixes not null violation for new not null column. Thanks @tcitworld.


## 2.8.0 - 2023.10.02

### ✨New
* 📝 Add toggle for full width page view. (#242)
* 👁️ Allow to collapse recent pages widget on landing page. (#835)
* 😀 Allow to unselect collective and page emojis. (#422)

### 🐛Fixes
* 🔎 Handle removed files gracefully when searching for page content. (#873)
* 💄 Fix several visual glitches in user interface. (#831)
* 📋 Improve documentation wording around public shares. Thanks @pjrobertson.
* ℹ️ Show collectives info box in Files app on Nextcloud 28. (#860)
* 🔨 Reorder page actions menu.
* 🐛 Fix spurious backend error log in page trash.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.7.1 - 2023.09.11

### 🐛Fixes
* 🧹 Fallback to old EventDispatcher on older releases, fixing background job. (#828)
* 📊 Fix async component loading, fixing mermaid support in view mode. (#866)
* ⚡️ Fix LazyFolder implementation with Nextcloud 28.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️  Update NPM dependencies.
* ⬆️  Update PHP composer dependencies.


## 2.7.0 - 2023.08.08

### ✨New
* 👥 Allow to manage members inside the app. (#308, #244)
* 🛬 List recent pages and members on landing page. (#311)
* ⌛ Placeholder skeleton instead of loading spinner while loading.
* 📊 With Nextcloud 27.1, add support for mermaid diagrams. (#284)
* 👩‍💻With recent Text versions, add syntax highlighting to code blocks.

### 🐛Fixes
* 🔗 Grow link area in page list to full item height. (#808)
* ⌨️  Use `autofocus` command from Text. With recent Text versions, cursor position
  will be restored when opening a page.
* 👩‍💻Use `OCP\IDispatcher` to fix issues with Nextcloud 27.1. (#710)
* 💄 Fix stickyness of titlebar when viewing old versions of a page.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️  Update NPM dependencies.
* ⬆️  Update PHP composer dependencies.


## 2.6.1 - 2023.07.10

### 🐛Fixes
* 🔗 Remove duplicate slash from links to app in search providers. (#762)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.6.0 - 2023.07.03

### ✨New
* 🗑️ Add pages trash to files backend and frontend. (#47)

### 🐛Fixes
* 🛰️ Make sure the default app route has a trailing slash. (#411, 727)
* 🔗 Fix relative paths to pages from index or landing pages. (#642, #684, #726)
* 🔗 Fix path to collective in navigation of public shares. (#697)
* 🗣️ Fix localization section for 'Select emoji'. Thanks to AIlkiv.
* 🎯 Always display action buttons in collectives trash on mobile.
* 📜 Remove old versions along with pages. (#136)
* 📜 Delete old versions when deleting a collective.
* 🗑️ Allow to trash pages with subpages.
* 🏛️ Switch to ObjectStoreScanner on object storage. (#744)
* 📋 Lower minimum page list width to 15%.
* 🔃 Don't change subpage order if a page gets renamed.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* 📋 Minor updates to development documentation.
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.5.0 - 2023.05.12

### ✨New
* 🔒 Support server side encryption. (#285)
* 🛡️ Flag circles for collectives as managed by app. (#314, 613)
* ✨ Add support for Nextcloud 27.

### 🐛Fixes
* 🔗 Fix relative links to non-markdown files in current collective. (#638, #642)
* 🐛 Fix error when creating first Collective. (#587)
* ♻️ Reset outline showing state when switching pages. (#619)
* 💄 Update sidebar components to current design. (#608)
* 🐛 Avoid call on null bug in PageService.
* 💄 Show title and description on "page not found" page.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* ⬆️ Update NPM dependencies.
* ⬆️ Update PHP composer dependencies.


## 2.4.0 - 2023.03.24

### ✨New
* Page attachments list in the sidebar. (#135, #322)

### 🐛Fixes
* Many improvements to link handling in view mode. (#385)
* Displaying non-image attachments in view mode. (#396)
* Use Collectives icon for landing page of collectives without emoji.

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* 🔌 Update NPM dependencies.
* 🔌 Update PHP composer dependencies.


## 2.3.0 - 2023.02.21

### ✨New
* 🍾 Support Nextcloud version 26.
* 🔗 Collective pages link picker. (Thanks Julien Veyssier, #509)
* 🌟 New dialog with member picker for creating a collective. (#464)
* 🚪 Add option to leave a collective for non-admins. (#465)

### 🐛Fixes
* 🖱️ Improve page drag and drop experience. (#423)
* 💄 Fix title and description of collective not found page.
* ⚙️ Make sure the collective settings modal is closed after deleting.
* 💄 Add collective name in browser title in print view. (Thanks @snehar97, #474)
* 🚀 Improve imports in filelist info box, shrinking the JS file from 4MB to 100KB.
* ⚙️ Improve settings icon in dark mode. (#546)
* 📝 Don't focus editor when switching to edit mode.
* 📘 Don't load emoji picker if collective is readonly.
* 🖱️ Disable drag'n'drop sorting/moving pages in readonly mode.
* 🔨 Show page actions menu in readonly mode.
* 🆕 Fixed broken layout of create collective form. (Thanks Ferdinand Thiessen, #548)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* 🔌 Update NPM dependencies.
* 🔌 Update PHP composer dependencies.


## 2.2.1 - 2023.01.08

### 🐛Fixes
* 🔎 Copy tntsearch library into release tarball. (#492)

### 🌎Translations
* 🗣️ Translation updates from Transifex. Thanks to all contributors.


## 2.2.0 - 2023.01.05

### ✨New
* 🎯 Add modal to move pages without drag&drop. (#462)
* 🚚 Allow to toggle outline view in view mode. (#410)
* ℹ️ In Files app, show a infobox linking to Collectives app. (#138)
* 🤸 Add API to register extra collective action.
* 💱 Migrated project from Gitlab to Github.

### 🐛Fixes
* 🔗 Fix Heading anchor links in view mode. (#395)
* 🖱️ Fix scrolling to image in view mode. (#392)
* 📂 Allow to configure default app folder location.
* 🚀 Performance improvements when building page list.
* 🧹 Ignore attachments folder when deleting/renaming a page. (#468)

### 🌎Translations
* 💱 Switch from Weblate to Transifex for translations.
* 🗣️ Danish translation updated thanks to Jens Peter Nielsen.
* 🗣️ Korean translation added thanks to SeungJu Lee.
* 🗣️ Slovenian translation updated thanks to Matej U.
* 🗣️ German translations updated thanks to Joachim Sokolowski.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ Russian translation updated thanks to Andrey Rozentals.
* 🗣️ Lots of translation updates from Transifex. Thanks to all contributors.

### 🚧Updates & Tooling
* 🔌 Update NPM dependencies.
* 🔌 Update PHP composer dependencies.


## 2.1.1 - 2022.11.29

### 🐛Fixes
* 📱 Fix width of page title in page list when actions are visible. (#425)
* 🧹 Reset page content when switching pages. (#430)
* 💄 Only show action to show page sidebar in page title actions menu.
* 🎯 Show collective actions in actions menu of landing page. (#435)

### 🌎Translations
* 🗣️ Restore Dutch translation of skeleton file

### 🚧Updates & Tooling
* 🔌 Update NPM dependencies.


## 2.1.0 - 2022.11.25

### ✨New
* 📝 Add collective setting for default page mode. (#418)
* 🔥️️ Drop support for PHP 7.3, PHP 7.4 code enhancements.

### 🐛Fixes
* 📱 Improve and consolidate page title on mobile. (#388)

### 🚧Updates & Tooling
* 🔌 Update PHP composer dependencies.

## 2.0.0 - 2022.10.25

### ✨New
* 💄 Migrate to Nextcloud 25 design.
* 🍾 Support Nextcloud version 25.
* 🧹 Drop support for Nextcloud version 24 and earlier.

### 🐛Fixes
* 🔎 Consider landing pages in fulltext search results. (#391)
* 🖱️ Fix moving pages in public shares.
* 👽 Force-setup the full filesystem if collective folder not found. (#332)
* 🎩 Start html title with actual page name. (#361)
* 🧹 Hide unneeded UI elements in public share.
* 🙍 Display users display name in frontend of user id. (#359)
* Fix max width of the page list item.
* Fix auto-expansion of parent pages in page list.
* Fix and document usage for users with quota 0B (e.g. guest users). (#231)
* Use default font size for page list items. (#373)
* Encode collective name in share URL. (#401)
* Timeout when loading missing images in print view. (#333)

### 🌎Translations
* 🗣️ Czech translation updated thanks to Pavel Borecki.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ Danish translation updated thanks to Jens Peter Nielsen.
* 🗣️ German translations updated thanks to Joachim Sokolowski.
* 🗣️ Slovenian translation added thanks to Matej U.
* 🗣️ Polish translation added thanks to Norbert Truszkiewicz.
* 🗣️ French translation updated thanks to Nathan.

### 🚧Updates & Tooling
* 🔌 Update NPM dependencies.
* 👷 Don't include composer dev packages in release builds.


## 1.5.0 - 2022.09.08

### ✨New
* 🔃 Allow to sort pages in custom order. (#303)
* 🖱️ Move pages between subpages via drag and drop. (#252)

### 🌎Translations
* 🗣️ Danish translation added thanks to Jens Peter Nielsen.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ German translations updated thanks to Joachim Sokolowski.
* 🗣️ Chinese translation updated thanks to Jason Clermont.


## 1.5.0-beta1 - 2022.08.11

### ✨New
* 🔃 Allow to sort pages in custom order. (#303)
* 🖱️ Move pages between subpages via drag and drop. (#252)


## 1.4.3 - 2022.08.11

### 🐛Fixes
* 🔝 Don't try to persist user-selected page order in public shares.
* 🚀 Fix search index occ commands and background jobs without sqlite. (#371)

### 🌎Translations
* 🗣️ Czech translation updated thanks to Pavel Borecki.
* 🗣️ Dutch translation updated thanks to Jeroen Bos.
* 🗣️ French translation updated thanks to Nathan.


## 1.4.2 - 2022.08.08

### 🐛Fixes
* 🥟 Really fix sticky editor menubar. (#367)
* 💄 Fix sticky app-details toggle.


## 1.4.1 - 2022.08.05

### 🐛Fixes
* 🔎 Fix search errors when sqlite is not available.
* 🥟 Fix sticky editor menubar. (#367)
* ☝️ Migrate icons for search providers to pure CSS+SVG. (#366)

### 🚧Updates & Tooling
* 🔌 Update NPM dependencies.


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
