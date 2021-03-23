# Changelog

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
