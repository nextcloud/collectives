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

## Install Nextcloud Collectives

The **Collectives** app can be installed from the [Nextcloud App Store](https://apps.nextcloud.com/apps/collectives).

In your Nextcloud instance, simply navigate to **»Apps«**, find the
**»Collectives«** app and enable it.

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
