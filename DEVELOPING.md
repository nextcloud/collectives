## Build-time Dependencies

The following tools are required for app development:

* make: to run the Makefile targets
* curl: to fetch some build tools from the web
* npm: to install NodeJS dependencies and compile JS assets
* g++: to compile some NodeJS dependencies
* gettext: to generate pot/translation files
* rsync and openssl: for generating release tarballs
* composer for installing php dependencies
* nextcloud server: for running php tests
* circles app: for passing some php tests that depend on it.

## Developer installation

To install the app manually:

0. Install a [development setup](https://docs.nextcloud.com/server/21/developer_manual/app_development/tutorial.html#setup) of nextcloud.
1. Clone this into the `apps` folder of your Nextcloud
2. Install build tools and dependencies by running `make dev-setup`
3. Compile NodeJS assets by running `make build`
4. Install the circles app in Nextcloud.

Afterwards, you can enable the app from the Nextcloud app management menu.

## Running tests

With the app available in the Nextcloud app management
you should be able to run the unit tests with
```sh
make php-unit-test
```

In order to run the integration tests you either need to configure your
nextcloud to run with https and be availabe at `https://nextcloud.local`
or you need to change the `default` config for behat in
`tests/Integration/features/config/behat.yml`
to use a different `baseUrl`.

Then you can run them with
```sh
make php-integration-test
```

The integration tests rely test data installed to the server.
This is available on our docker image or in the
`nextcloud-docker-dev` repo.

### Development environment

Development environments often do not use proper hostnames and are not
using ssl. In order to make the Circles API work in such environments,
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

## Development Background: Collective ownership

Usually, in Nextcloud every file/folder is owned by a user. Even when shared,
the ultimate power over this object remains at the owner user. In collective
workflows, this leads to several problems. Instead of individual users,
we want the collective pages to be owned and maintained by the collective.

That's why the Collectives app implements an own storage and uses mountpoints
to mount the collective folders to members home directories.

## Development Background: Circles integration

Every collective is bound to a circle. Currently, the app automatically creates
a new secret circle with every new collective.

## Prepare a release

Dependencies for building a new release:

* Nextcloud OCC at `../../occ` and required PHP dependencies
* App certificate+key for signing the app at `~/.nextcloud/certificates`

Releasing a new version contains the following steps:

* Update `CHANGELOG.md`
* Bump version in `appinfo/info.xml`
* Make sure the Gitlab CI passes
* Build the JS assets from a clean state:
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
* Publish new app version in Nextcloud App Store

## Update javascript dependencies

Update all dependencies right after a release
so they will be tested for a while before the next release.

After installing `npm-check-updates` with
```
npm install npm-check-updates --no-save
```
List all outdated packages with `npm run npm-check-updates`
and then updat all of them with the `-u` option.

Roll back updates that brake the build with
```
npm install package@^1.2.3
```

Note in the commit message why packages rolled back to an earlier version.
This information makes the next version update easier.
