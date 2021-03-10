+++
title = "Developer Documentation"
description = ""
weight = 3
alwaysopen = false
+++

## Prepare a release

Dependencies for building a new release:

* `npm` (for `npm ci`)
* Nextcloud OCC PHP dependencies: `php-cli php-curl, php-gd, php-mbstring, php-pgsql, php-zip, php-xml`
* `rsync` and `openssl`
* App certificate+key for signing the app at `~/.nextcloud/certificates`

Releasing a new version contains the following steps:

* Update `CHANGELOG.md`
* Bump version in `appinfo/info.xml`
* Make sure the Gitlab CI to passes
* Update node.js dependencies and build the JS assets:
  ```
  npm ci
  make clean
  make build-js-production
  ```
* Copy files to release directory, sign files and pack them in a release tarball:
  ```
  make release
  ```
* Upload release tarball to Gitlab, add release tag and publish releas on Gitlab
