<!--
  - SPDX-FileCopyrightText: 2020-2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

## Build-time Dependencies

The following tools are required for app development:

* make: to run the Makefile targets
* curl: to fetch some build tools from the web
* npm: to install NodeJS dependencies and compile JS assets
* g++: to compile some NodeJS dependencies
* rsync and openssl: for generating release tarballs
* php `dom` and `sqlite` extension
* composer for installing php dependencies
* nextcloud server for running php tests
* teams/circles app for passing some php tests that depend on it
* gh, the Github console command, for releasing to Github

## Developer installation

To install the app manually:

0. Install a [development setup](https://docs.nextcloud.com/server/latest/developer_manual/app_development/tutorial.html#setup) of nextcloud.
1. Install the teams/circles, text and viewer apps by cloning them to the `apps` folder
2. Clone this repository into the `apps` folder of your Nextcloud
3. Install build tools and dependencies by running `make setup-dev`
4. Compile NodeJS assets by running `make build`

Afterwards, you can enable the app from the Nextcloud app management menu.

## Running tests

With the app available in the Nextcloud app management  you should be able to
run the unit tests with
```
make test-php-unit
```

In order to run the integration tests you either need to configure your
Nextcloud to run with https and be availabe at `https://nextcloud.local`,
or you need to change the `default` config for behat in
`tests/Integration/features/config/behat.yml`
to use a different `baseUrl`.

Then you can run them with
```
make test-php-integration
```

The integration tests rely on test data installed to the server. This is
available in the `nextcloud-docker-dev` repo.

### Development environment

Development environments often do not use proper hostnames and are not
using ssl. In order to make the teams API work in such environments,
a few configuration settings need to be adjusted.

You can do so by running the following commands on the nextcloud server:
```
./occ config:system:set --type bool --value true -- allow_local_remote_servers
./occ config:app:set --value 1 -- circles self_signed_cert
./occ config:app:set --value 1 -- circles allow_non_ssl_links
./occ config:app:set --value 1 -- circles local_is_non_ssl
```

## Important developer links

* [Nextcloud Vue Style Guide](https://nextcloud-vue-components.netlify.app/)

### Faster frontend developing with HMR

You can enable HMR (Hot module replacement) to avoid page reloads when working
on the frontend:

1. ☑️ Install and enable [`hmr_enabler` app](https://github.com/nextcloud/hmr_enabler)
2. 🏁 Run `npm run serve`
3. 🌍 Open the normal Nextcloud server URL (not the URL given by above command)

## Development Background: Collective ownership

Usually, in Nextcloud every file/folder is owned by a user. Even when shared,
the ultimate power over this object remains at the owner user. In collective
workflows, this leads to several problems. Instead of individual users,
we want the collective pages to be owned and maintained by the collective.

That's why the Collectives app implements an own storage and uses mountpoints
to mount the collective folders to members home directories.

## Development Background: Teams integration

Every collective is bound to a team. Currently, the app automatically creates
a new secret team with every new collective.

## Prepare a release

Dependencies for building a new release:

* Nextcloud OCC at `../../occ` and required PHP dependencies
* App certificate+key for signing the app at `~/.nextcloud/certificates`

Releasing a new version contains the following steps:

* Update `CHANGELOG.md`
* Bump version in `appinfo/info.xml`
* Make sure the Github CI passes
* Build release and publish to Github and App Store:
  ```
  make release-github
  make release-appstore
  ```

## Backport changes to `stableX` branches

App development happens in the `main` branch. From time to time, we have to
branch off due to backwards-incompatible changes in the Nextcloud server code.
In these cases, we create a new branch like `stable1` (for 1.X releases) that
holds the Collectives version before we break compability with an old release.

To allow backporting changes from the `main` branch to these `stableX` releases
later, we established the following workflow:

The last backported commit from `main` is tagged as `backported`. In order to
backport all  subsequent commits and prepare a `stableX` branch for a new
release, do the following:

1. Backport commits since `backported` (replace `stableX` with the branch name):
   ```
   git checkout origin/main -b backport/stableX
   git rebase --onto stableX backported -i
   git push origin backport/stableX
   git tag -d backported
   git push origin --delete backported
   git tag backported origin/main
   git push origin --tags
   ```
2. Create a merge request from `backport/stableX` to `stableX`. Merge after
   pipeline succeeds.
3. Remove temporary branches:
   ```
   git branch -D backport/stableX
   git push origin --delete backport/stableX
   ```
