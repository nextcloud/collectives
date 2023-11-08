+++
title = "Administrator Documentation"
description = ""
weight = 2
alwaysopen = false
+++

## Runtime Dependencies

This app requires the following apps to be enabled:

* [**Circles**](https://apps.nextcloud.com/apps/circles)
* [**Text**](https://apps.nextcloud.com/apps/text)
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

## Collectives and guest users

In order to allow guest users (as provided by the [guests](https://github.com/nextcloud/guests/)
app) to access collectives, add the Collectives and Circles apps to the list
of enabled apps for guest users in admin settings.

Please note that this enables guest users to create new collectives.

## Searching Collectives

To enable searching of collectives from the unified Nextcloud search, make sure the `ext-pdo` and `ext-pdo_sqlite` PHP extensions are installed and the Nextcloud cronjob is running. The index of collectives page contents should update with every cronjob run. The following commands can be used to install the PHP extensions on Ubuntu:

```
apt-get install phpXX-mysql # for Nextcloud instances that use MySQL
apt-get install phpXX-pgsql # for Nextcloud instances that use Postgres
apt-get install phpXX-sqlite3 # required for all instances
```

Note: the XX above should be replaced with your PHP version. E.g. ` php8.1-sqlite3` for PHP 8.1


## Public shares

WebDAV access to public shares must not be disabled (i.e it must be enabled)
for publically shared collectives to work. Please make sure that the following
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

You can configure the circles app to allow adding groups to circles.
Since the collectives app relies on the circles app for user management
this also allows adding entire groups to collectives.

Keep in mind thought that in contrast to circles
groups can only be managed by server admins.
