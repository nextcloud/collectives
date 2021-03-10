## Build-time Dependencies

The following tools are required for app development:

* make: to run the Makefile targets
* curl: to fetch some build tools from the web
* npm: to install NodeJS dependencies and compile JS assets
* g++: to compile some NodeJS dependencies
* gettext: to generate pot/translation files
* rsync and openssl: for generating release tarballs

## Developer installation

To install the app manually:

1. Clone this into the `apps` folder of your Nextcloud
2. Install build tools and dependencies by running `make dev-setup`
3. Compile NodeJS assets by running `make build`

Afterwards, you can enable the app from the Nextcloud app management menu.

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
* Publish new app version in Nextcloud App Store
