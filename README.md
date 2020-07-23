# Collective Wiki

The CollectiveWiki app provides simple wiki functionalities to collectives.
It facilitates to record and organize knowledge in a collaborative way.

* **Collective and non-hierarchical workflow by heart**: Wikis are tied to
  a [Nextcloud Circle](https://github.com/nextcloud/circles) and owned by
  the collective.
* **Collaborative page editing** like known from Etherpad thanks to the
  [Text app](https://github.com/nextcloud/text)
* **Well-known [Markdown](https://en.wikipedia.org/wiki/Markdown) syntax**
  for page formatting

## Installation

The app is *not* published yet to the Nextcloud app store, so you have to
install it manually:

1. Clone this into the `apps` folder of your Nextcloud
2. Install PHP dependencies by running `composer install`
3. Install NodeJS dependencies by running `npm install`
4. Compile NodeJS assets by running `npm run build`

Afterwards, you can enable the app from the Nextcloud app management menu.

## Maintainers

* Azul <azul@riseup.net>
* Jonas <jonas@freesources.org>

## Licence

Files: *
Copyright: (c) 2019-2020 Azul <azul@riseup.net>
           (c) 2019-2020 Jonas <jonas@freesources.org>
License: AGPL v3 or later

Files: images/app.svg
Copyright: (c) 2012-2018 Dave Gandy <drgandy@alum.mit.edu>
License: CC-BY-4.0
