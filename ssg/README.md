<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# Static Site Generator (SSG) for Collectives

This directory contains the **Hugo**-based static site generator used by the
Collectives app to render static HTML sites from collective content.

> **Status: proof of concept.** Today it renders a bundled *sample* site to
> demonstrate the toolchain end-to-end. The next step is to feed real collective
> pages (Markdown) into the build instead of the sample content.

## Why a static site generator?

Collectives are written in Markdown and edited collaboratively. A static site
generator lets us turn a *scope* of a collective (later: selected pages) into a
self-contained set of HTML files that can be archived, downloaded, or published
without needing a running Nextcloud.

[Hugo](https://gohugo.io/) was chosen because it is **lightweight**: it ships as
a **single static binary** with no runtime dependencies (no Node, no
`node_modules`), it has first-class Markdown support, and it builds extremely
fast. This keeps the runtime requirement to "one executable on disk".

## Architecture overview

```
 Browser (Vue)                     PHP backend                       Hugo
 ─────────────                     ───────────                       ────
 NcActionCollective-               StaticSiteController (OCS)         ssg/.runtime/hugo
   Actions.vue                       │                                  (single binary)
   "Generate static site"  ──POST──▶ │                                       ▲
                                     ▼                                       │
                            StaticSiteService                                │
                              1. locate Hugo binary  ──────────────────────────┘
                              2. proc_open `hugo --source ssg/hugo --destination temp --noBuildLock`
                              3. store the generated index.html in the user's files
                                     │
                                     ▼
                            /<user>/files/Collectives Static Sites/<name>-<timestamp>.html
```

### Request flow

1. The user clicks **Generate static site** in the collective actions menu
   (`src/components/Collective/NcActionCollectiveActions.vue`).
2. The frontend calls `generateStaticSite()`
   (`src/apis/collectives/staticSite.js`), which `POST`s to the OCS endpoint
   `POST /ocs/v2.php/apps/collectives/api/v1.0/staticsite`.
3. `StaticSiteController::create()` delegates to
   `StaticSiteService::generateSampleSite()`.
4. The service runs `hugo` and stores the resulting HTML in the user's files,
   returning the relative `path` of the generated file.
5. The frontend shows a success toast with the saved path.

## Directory layout

```
ssg/
├── README.md            ← this file
├── fetch-hugo.sh        ← downloads a portable Hugo binary into .runtime/
├── .runtime/            ← portable Hugo binary (git-ignored, created by fetch-hugo.sh)
│   └── hugo
└── hugo/                ← the Hugo project
    ├── hugo.toml        ← site config (minimal: only the homepage is rendered)
    └── layouts/
        └── index.html   ← self-contained homepage template
```

## The runtime problem (and solution)

Hugo needs its **binary at runtime** (when the button is clicked), but the
Nextcloud PHP container ships **without Hugo**.

Solution: a **portable Hugo binary is placed inside this app directory**
(`ssg/.runtime/hugo`). Because the app directory is bind-mounted into the
container and the host and container share the same OS/architecture
(`linux-amd64` in the dev setup), the same binary works in both places and
survives container restarts — without modifying the container image.

`StaticSiteService::getHugoBinary()` looks for the binary in this order:

1. `ssg/.runtime/hugo` (portable binary)
2. `/usr/bin/hugo`, `/usr/local/bin/hugo` (system Hugo, if present)

If none is found it throws `MissingDependencyException`.

## How the backend invokes Hugo

`StaticSiteService` is intentionally minimal — the whole flow is just *run the
binary, then save the result*. It uses PHP's `proc_open()` (array form, so **no
shell** is involved) to build directly from the app directory into a temporary
output directory:

```
<hugo> --source ssg/hugo --destination <temp> --noBuildLock --quiet
```

`--noBuildLock` stops Hugo from writing a `.hugo_build.lock` into the (read-only)
app directory, so **no project copy and no cache handling are needed** — Hugo
only writes to the temp `--destination`.

Parameters are passed to Hugo templates via environment variables, which the
templates read with `os.Getenv` (allow-listed in `hugo.toml` under
`[security.funcs]`):

| Env variable             | Purpose                                |
| ------------------------ | -------------------------------------- |
| `COLLECTIVES_SSG_TITLE`  | Title rendered on the sample page      |
| `COLLECTIVES_SSG_USER`   | User shown in the page footer          |

The homepage template (`hugo/layouts/index.html`) is fully self-contained
(inline CSS), so the generated `index.html` is a single file. The service writes
it into the user's files (via `IRootFolder`) as
`Collectives Static Sites/<title>-<timestamp>.html`, then removes the temp
directory. (A real multi-file site would copy the whole output directory instead
of a single file — that is the natural next step.)

## Setup

From the app root:

```bash
make ssg-setup
```

This downloads a portable Hugo binary into `ssg/.runtime/` (`fetch-hugo.sh`).
That is the only setup step — there are no further dependencies to install.

In the dev Docker setup, where the host has no direct internet but the container
does, run the download inside the container (the app dir is bind-mounted):

```bash
docker exec master-nextcloud-1 sh -c \
  'cd /var/www/html/apps-extra/collectives && sh ssg/fetch-hugo.sh'
```

The Hugo version and architecture can be overridden via env vars, e.g.
`HUGO_VERSION=0.140.2 HUGO_ARCH=linux-amd64 sh ssg/fetch-hugo.sh`.

## Manual testing

Run a build directly (bypassing PHP), writing to a temp folder:

```bash
cd ssg/hugo
COLLECTIVES_SSG_TITLE="Demo" COLLECTIVES_SSG_USER="alice" \
../.runtime/hugo --source . --destination /tmp/ssg-out --quiet
ls /tmp/ssg-out   # → index.html
```

Exercise the full endpoint (build + store in the user's files):

```bash
curl -s -u admin:admin -H "OCS-APIRequest: true" -H "Accept: application/json" \
  -X POST "http://nextcloud.local/ocs/v2.php/apps/collectives/api/v1.0/staticsite" \
  --data "title=Hugo Demo Collective"
# → {"ocs":{...,"data":{"path":"/Collectives Static Sites/Hugo Demo Collective-....html"}}}
```

The generated site appears in the **Files** app under
`Collectives Static Sites/<name>-<timestamp>.html`.

## Relevant source files

| Concern        | File                                                          |
| -------------- | ------------------------------------------------------------ |
| Hugo project   | `ssg/hugo/` (`hugo.toml`, `layouts/index.html`)             |
| Hugo runtime   | `ssg/fetch-hugo.sh`, `make ssg-setup`                       |
| Backend logic  | `lib/Service/StaticSiteService.php`                          |
| OCS endpoint   | `lib/Controller/StaticSiteController.php`, `appinfo/routes.php` |
| Frontend API   | `src/apis/collectives/staticSite.js`                        |
| Button (UI)    | `src/components/Collective/NcActionCollectiveActions.vue`   |

## Building the frontend

The Vue assets require a recent Node.js (Vite 7 needs Node ≥ 20.19 / 22.12).
The bundled Hugo runtime does **not** include Node, so build the frontend with
your own toolchain, e.g. via nvm:

```bash
nvm install 24 && nvm use 24
npm run dev      # or: npm run build
```

## Roadmap / next steps

- Replace the sample content with **real collective pages** (render the
  Markdown tree of a selected scope, which maps naturally onto Hugo content).
- Add a **scope/page selector** in the UI before generating.
- Support a **downloadable archive** (zip) of the generated site.
- Make the SSG **pluggable** so other generators can be added alongside Hugo.
