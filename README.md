# Collectives

Collectives is a Nextcloud App for activist and community projects to
organize together. Come and gather in collectives to build shared knowledge.

* 👥 **Collective and non-hierarchical workflow by heart**: Collectives are
  tied to a [Nextcloud Circle](https://github.com/nextcloud/circles) and
  owned by the collective.
* 📝 **Collaborative page editing** like known from Etherpad thanks to the
  [Text app](https://github.com/nextcloud/text).
* 🔤 **Well-known [Markdown](https://en.wikipedia.org/wiki/Markdown) syntax**
  for page formatting.
* 🔎 Full-text search with automatic indexing to find content straight away.

![Screenshot of Nextcloud Collectives Version 0.2.1](https://raw.githubusercontent.com/nextcloud/collectives/main/docs/static/images/screenshot.png)

## Installation

In your Nextcloud instance, simply navigate to **»Apps«**, find the
**»Circles«** and **»Collectives«** apps and enable them.

## Requirements

For full-text search to work the sqlite and pdo PHP extensions must be installed.

## Documentation and help

Take a look at our [online documentation](https://nextcloud.github.io/collectives/).

Also, don't hesitate to ask [the community](https://help.nextcloud.com/c/apps/collectives/174)
for help in case of questions.

## Developer documentation

Documentation for developers can be found at [DEVELOPING.md](DEVELOPING.md).

## Development setup
This app requires [Text](https://github.com/nextcloud/text) and [Circles](https://github.com/nextcloud/circles).
You also need nvm v16.16.0 to compile the JavaScript. 
After installing Text and Circles:

1. Clone Collectives into the apps folder of your Nextcloud: 
   `git clone https://github.com/nextcloud/collectives`
3. In the folder of the app, run the command `make` to install dependencies and build the Javascript.
4. Enable the app through the app management of your Nextcloud
5. 🎉 Partytime! Help fix some issues and review pull requests 👍


## Translations

Project translations are [managed on Transifex](https://www.transifex.com/nextcloud/nextcloud/collectives/).

## Maintainers

* Azul and Jonas <collectivecloud@systemli.org>

## Licence

AGPL v3 or later. See [COPYING](COPYING) for the full licence text.

The app logo and page icons were designed and contributed by Jörg Schmidt
from Institut für Gebrauchsgrafik <info@institut.gebrauchsgrafik.org>.
