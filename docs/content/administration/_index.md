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

### Custom skeletons for new collectives

It's possible to create custom skeletons for new collectives by putting files
in the app skeleton directory at `data/app_<INSTANCE_ID>/collectives/skeleton`.
New collectives get the contents of this skeleton directory copied over.

`README.md` is the landing page that is opened automatically when entering a
collective.

If the skeleton directory doesn't contain a `README.md`, the default landing
page from `apps/collectives/skeleton/README.md` will be copied into the
collectives directory instead.
