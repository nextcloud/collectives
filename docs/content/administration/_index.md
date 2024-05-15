+++
title = "Administrator Documentation"
description = ""
weight = 2
alwaysopen = false
+++

## Runtime Dependencies

Collectives requires the following apps to be enabled, all being shipped and enabled by default with recent Nextcloud releases:

* **Teams** (with Nextcloud >= 29) or **Circles** (with Nextcloud <= 28)
* **Text**
* **Viewer**
* **files_versions**

## Install Nextcloud Collectives

The **Collectives** app can be installed from the [Nextcloud App Store](https://apps.nextcloud.com/apps/collectives).

In your Nextcloud instance, simply navigate to **»Apps«**, find the
**»Collectives«** app and enable it.

## Collectives and server-side encryption

With server-side encryption enabled, the files in a Collective will be stored
encrypted on the filesystem as well.

Please note that index files for the full-text search will not be encrypted
though. Also, please read the [Nextcloud server documentation about
limitations](https://docs.nextcloud.com/server/latest/admin_manual/configuration_files/encryption_configuration.html#files-not-encrypted) carefully.

## Collectives and `group_everyone`

When using the [group_everyone app](https://github.com/icewind1991/group_everyone/), existing
users will not see collectives with the "everyone" group as member. The group members need to
be synced once in the circles app: `occ circles:sync --groups`

This only needs to be done once. New users that got created after the app was enabled will see
the collectives straight away
.

## Collectives and guest users

In order to allow guest users (as provided by the [guests](https://github.com/nextcloud/guests/)
app) to access collectives, add the Collectives and Teams apps to the list
of enabled apps for guest users in admin settings.

Please note that this enables guest users to create new collectives.

## Searching Collectives

To enable searching of collectives from the unified Nextcloud search, make sure the `ext-pdo` and `ext-pdo_sqlite` PHP extensions are installed and the Nextcloud cronjob is running. The index of collectives page contents should update with every cronjob run.

Tip: On Ubuntu 22.04, the relevant package to install is `phpXX-sqlite3` - with the XX being replaced with your PHP version. E.g. ` php8.1-sqlite3` for PHP 8.1.


## Public shares

WebDAV access to public shares must not be disabled (i.e. it must be enabled)
for publicly shared collectives to work. Please make sure that the following
 admin option is enabled and not disabled: "Allow users on this server to send
 shares to other servers (This option also enables WebDAV access to public shares)"
 under "Sharing -> Federated Cloud Sharing".

## Configuration

### Initial Content for new collectives

It's possible to create custom content for new collectives by putting files
in the app skeleton directory at `data/app_<INSTANCE_ID>/collectives/skeleton`.
New collectives start with the contents of this directory.

Create a `Readme.md` to change the landing page that is opened automatically
when entering a collective.

If the skeleton directory doesn't contain a `Readme.md`, the default landing
page from `apps/collectives/skeleton/Readme.md` will be copied into the
collectives directory instead.

### Allow for groups in your collectives

You can configure the teams app to allow adding groups to teams.
Since the collectives app relies on the teams app for user management
this also allows adding entire groups to collectives.

Keep in mind thought that in contrast to teams, groups can only be
managed by server admins.
