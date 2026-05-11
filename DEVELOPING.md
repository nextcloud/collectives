<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

## Development installation

To set up a development setup for Collectives, do the following:

1. Install a [development setup](https://docs.nextcloud.com/server/latest/developer_manual/app_development/tutorial.html#setup) of Nextcloud.
2. Install the following apps by cloning them into the `apps` folder:
   * [Teams](https://github.com/nextcloud/circles) (used to be called "Circles")
   * [Text](https://github.com/nextcloud/text)
   * [Viewer](https://github.com/nextcloud/viewer)
3. Clone this repository into the `apps` folder of your Nextcloud development instance.
4. Make sure, you have all required [build-time dependencies](#build-time-dependencies) installed.
5. Run `make setup-dev` to install the build tools and dependencies.
6. Run `make build-js-dev` to build the JavaScript assets.

Afterward, you can enable the app from the Nextcloud app management menu.

## Important developer links

* [Nextcloud Vue Style Guide](https://nextcloud-vue-components.netlify.app/)

## Running tests

### Playwright end-to-end tests

```
npx playwright test playwright/e2e/<test-file>.spec.ts
```

### Cypress end-to-end tests

```
CYPRESS_ncVersion='master' CYPRESS_baseUrl=https://nextcloud.local/index.php npx cypress run --spec cypres/e2e/<test-file>.spec.js
```

### Behat API integration tests

In order to run the integration tests you might need to change the `default` config for behat in
`tests/Integration/features/config/behat.yml` to use a different `baseUrl`.

The integration tests rely on test users being available in the server. They're auto-created when
using [nextcloud-docker-dev](https://github.com/juliusknorr/nextcloud-docker-dev).

```
make test-php-integration
```

### JavaScript unit tests

```
npm run test
```

### PHP unit tests
```
make test-php-unit
```

## Development environment

Development environments often do not use proper hostnames. In order to make the teams API
work in such environments, you might have to set 'overwrite.cli.url':

```
./occ config:system:set --value "https://nextcloud.local" -- overwrite.cli.url
```

### Faster frontend developing with HMR

You can enable HMR (Hot module replacement) to avoid page reloads when working
on the frontend:

1. ☑️ Install and enable [`hmr_enabler` app](https://github.com/nextcloud/hmr_enabler)
2. 🏁 Run `npm run serve`
3. 🌍 Open the normal Nextcloud server URL (not the URL given by above command)

## Development background: Collective ownership

Usually, in Nextcloud every file/folder is owned by a user. Even when shared,
the ultimate power over this object remains at the owner user. In collective
workflows, this leads to several problems. In Collectives, the collective data
is owned and maintained by the collective instead.

That's why the Collectives app implements an own storage and uses mountpoints
to mount the collective folders to members home directories.

## Development background: Teams integration

Every collective is bound to a team. The app automatically creates a new team
as member management backend when a new collective is created.

## Prepare a release

Dependencies for building a new release:

* Nextcloud `occ` command and required PHP dependencies
* App certificate+key for signing the app at `~/.nextcloud/certificates`
* rsync and openssl: for generating release tarballs
* gh, the GitHub console command, for releasing to GitHub

Releasing a new version contains the following steps:

* Update `CHANGELOG.md`
* Bump version in `appinfo/info.xml`
* Make sure the GitHub CI passes
* Build release and publish to GitHub and App Store:
  ```
  NEXTCLOUD_PASSWORD='...' OCC='../../occ' make release
  ```

## Backport changes to `stableX` branches

App development happens in the `main` branch. We try to maintain support for all officially
supported Nextcloud server versions in this branch.

If there's need for a bugfix release for an older version, we branch off. In these cases, we
create a new branch like `stable1` (for 1.X releases) from the last release tag that still
supported the required Nextcloud server version.

## Build-time dependencies

The following tools are required for building the app. Some might already be installed on your system:

* make: to run the Makefile targets
* curl: to fetch some build tools from the web
* npm: to install NodeJS dependencies and compile JS assets
* g++: to compile some NodeJS dependencies
