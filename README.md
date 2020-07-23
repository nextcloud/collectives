# CollectiveWiki

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

## Development Background: Circle and user management

In Nextcloud, every file/folder is owned by a user. Even when shared with a
circle, the ultimate power over this object remains at the owner user.In
collective workflows, this leads to several problems. Instead of individual
users, we want the documents to be owned and maintained by the collective.
Since this concept is unsupported by the Nextcloud and Circles per default,
we decided to implement it on our own.

Creating a new wiki internally does the following:

1. Create a vehicle user `wiki@<NAME>@<UUID>` (with random UUID as password)
2. Create a vehicle circle `wiki@<NAME>@<UUID>` with the vehicle user as owner
3. Create a wiki folder `wiki_<NAME>` with the vehicle user as owner
4. Share the wiki folder with the vehicle circle

TODO:
* Add support to rename and delete wikis
* Add support to maintain circle members (and their privilege level?)

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
