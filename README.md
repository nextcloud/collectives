<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# ⭐ Collectives

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/collectives)](https://api.reuse.software/info/github.com/nextcloud/collectives)

Your space to collaboratively write and organize. Collectives is designed for groups and communities
to structure shared knowledge and cultivate trust.

![Screenshot of Nextcloud Collectives](https://raw.githubusercontent.com/nextcloud/collectives/main/docs/assets/desktop-screenshot.png)

## Why use Collectives?

* **👥 Non-hierarchical at its core**: Each collective is backed by a
  [Nextcloud Team](https://docs.nextcloud.com/server/latest/user_manual/en/groupware/contacts.html#teams) - its
  content is owned by the group, not a single user.
* **📝 Collaborative page editing** thanks to the [Text app](https://github.com/nextcloud/text).
* **🔤 Well-known [Markdown](https://en.wikipedia.org/wiki/Markdown) syntax** for page formatting.
* **🔎 Full-text search** to find content straight away.

## 📚 Documentation

* **[📙 Documentation for users and user groups](https://docs.nextcloud.com/server/latest/user_manual/en/collectives/index.html)**
* **[📗 Documentation for administrators](https://docs.nextcloud.com/server/latest/admin_manual/collectives/index.html)**
* **[📘 Documentation for developers](DEVELOPING.md)**
* **[⚙️ OCS API](openapi.json)** - best viewed with the [OCS API Viewer app](https://apps.nextcloud.com/apps/ocs_api_viewer)

## 💬 Contact

In case of questions or feedback, you can join [the community](https://help.nextcloud.com/tag/collectives-app).

We're also available at [our public Collectives conversation](https://cloud.nextcloud.com/call/2618694936) if you
want to join the discussion.

## 🚧 Development setup

See [DEVELOPING.md](DEVELOPING.md) to set up a development environment and learn how to contribute.

## 📦 Installation

Install directly from the [Nextcloud App Store](https://apps.nextcloud.com/apps/collectives).

## 📤 Static site export (Hugo)

A page and all of its subpages can be exported as a self-contained static HTML website.
The pages are rendered with [Hugo](https://gohugo.io/) and downloaded as a `.zip` archive
(open `index.html` from the extracted folder to browse the site offline).

This feature requires the **`hugo` binary** to be available to the Nextcloud server process
(i.e. inside the container/host that runs PHP, not just your workstation).

### Install Hugo

```bash
# Debian/Ubuntu
apt-get update && apt-get install -y hugo

# or download a release binary from https://github.com/gohugoio/hugo/releases
```

### Configure the binary path (optional)

If `hugo` is on the web server's `$PATH`, it is detected automatically. Otherwise, point the
app at an absolute path:

```bash
occ config:app:set collectives hugo_binary --value=/usr/bin/hugo
```

If Hugo cannot be found, the export request fails with a descriptive error.

## 🔣 Translations

Collectives translations are managed as part of the [Nextcloud project on Transifex](https://explore.transifex.com/nextcloud/nextcloud/).

## ©️ License

AGPL v3 or later. See [COPYING](COPYING) for the full license text.
