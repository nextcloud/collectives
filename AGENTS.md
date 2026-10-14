<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# AGENTS.md

Nextcloud Collectives. PHP backend (`lib/`) + Vue.js frontend (`src/`).

---

## Generic notes

In frontend, always generate Typescript files when creating new files. Existing `.js` specs are not a template for new code.

For new e2e tests, always generate Playwright tests. Where appropriate, suggest to migrate Cypress tests to Playwright. The goal is to migrate completely from Cypress to Playwright in the future.

## Contributions

- Conventional Commits with a scope (e.g. `fix(versions): ...`).
- AI-assisted commits get an `Assisted-by: <Tool>:<model>` trailer.
- Changed or added code needs unit tests: Vitest in `src/tests/`, PHPUnit in `tests/Unit/`.
- New files need SPDX headers (REUSE CI enforces this).
- Comments describe the code, not the change that produced it. 
- Keep PRs to one concern. Disclose AI use in the PR description (the template has a section for it).

## Commands

### Install

```bash
npm ci                    # JS deps
composer install          # PHP deps (uses platform php=8.2 regardless of local PHP)
```

### Build

```bash
npm run build             # Production (Vite, NODE_ENV=production)
npm run dev               # Dev build
```

### Frontend tests (Vitest, jsdom, no server needed)

```bash
npm run test              # Run all unit tests once
npx vitest run src/tests/util/sortOrders..spec.js  # Single file
npx vitest run -t "pattern"                        # Filter by name
```

Unit specs live in `src/tests/**/*.spec.{js,ts}`; E2E specs live in `playwright/e2e/`.

### PHP unit tests (requires full Nextcloud server install — see below)

```bash
composer run test:unit    # phpunit -c tests/phpunit.xml
vendor/bin/phpunit --filter testMethodName tests/Unit/Service/EmojiHelperTest.php
```

PHPUnit should only be run with the user's consent (see Testing gotchas).

### Lint / typecheck / format

```bash
npm run lint              # tsc typecheck + ESLint
npm run lint:fix          # ESLint auto-fix
npm run stylelint         # Stylelint for CSS/SCSS/Vue styles
composer run lint         # PHP syntax check (php -l)
composer run cs:check     # PHP code style check (php-cs-fixer --dry-run)
composer run cs:fix       # php-cs-fixer auto-fix
composer run psalm        # Psalm static analysis (level 4, baseline: tests/psalm-baseline.xml)
composer run rector       # Rector refactoring (followed by cs:fix)
```

### E2E — Playwright (newer suite)

```bash
npx playwright install chromium --only-shell   # One-time browser install
npx playwright test
npx playwright test playwright/e2e/settings.spec.ts
npx playwright test --grep "pattern"
```

- Default URL: `http://localhost:8089/index.php/`. Unless a server already runs there, `playwright.config.ts` starts Nextcloud in Docker (`playwright/start-nextcloud-server.js`).
- CI runs 4 shards.
- Setting the `baseURL` env var skips the Docker `webServer`, but most tests still need the container: the fixtures `random-user.ts` and `Collective.ts` create test users with `createRandomUser()` from `@nextcloud/e2e-test-server/playwright`, which runs `occ` inside it.

#### Optional: running against an existing local Nextcloud instance

1. Build first (`npm run dev`) if not already done, since the instance serves `js/` and `css/` from this checkout.
2. Run the tests from a scratch copy whose `random-user.ts` and `Collective.ts` create users via OCS (`POST /ocs/v2.php/cloud/users` with `OCS-APIRequest: true` and admin credentials) instead of `createRandomUser()`.
3. Set `baseURL`. If Node doesn't trust the instance's certificate, also set `NODE_TLS_REJECT_UNAUTHORIZED=0`:
   ```bash
   baseURL=https://nextcloud.local/index.php/ \
     npx playwright test playwright/e2e/page-attachments.spec.ts --project=chromium
   ```

Tests that run `occ` directly still fail without the container: `page-content.spec.s` (whiteboard), `page-share.spec.ts`, `unified-search.spec.ts`, `version-comparison.spec.ts`, and collectives created with `markdownImportPath`.

### E2E — Cypress (deprecated, requires Nextcloud server)

```bash
npx cypress open                  # Interactive Cypress UI (run from repo root)
npx cypress run --spec "cypress/e2e/files.spec.js"   # Single spec
```

- Default server URL: `http://localhost:8081/index.php`. Override with `CYPRESS_baseUrl`.
- CI uses 4 parallel runners via `cypress-split`.

## Testing gotchas

**Behat integration tests** require a live Nextcloud reachable at `https://nextcloud.local`
(the default Behat profile).

**PHPUnit** should not be run without manual consent as it might brick the development instance.

---

## Architecture notes

- **Multiple frontend entry points** (`vite.config.ts`): `icons`, `init`, `main`, `reference`, `settings-admin`; the PWA service worker is also compiled from `src/`.
- **Built assets are not committed.** `js/` and `css/` contain compiled output but it's not tracked in Git.
- **Version lives only in `appinfo/info.xml`** — not in `package.json` or `composer.json`.
- **vendor-bin isolation.** PHP dev tools (phpunit, psalm, php-cs-fixer, behat, openapi-extractor) each have their own scoped Composer install under `vendor-bin/`. They are accessible via `./vendor/bin/` symlinks.

## OpenAPI

Run `composer run openapi` after any OCS API change. The `openapi.yml` CI workflow fails if
`openapi.json` has uncommitted changes.

## Psalm

- Error level 4, PHP 8.2 target; baseline at `tests/psalm-baseline.xml`
- Run `composer run psalm:update-baseline` to update the baseline (requires review — don't use
  it to silently suppress new issues)

## License compliance (REUSE)

Every new file needs SPDX headers (as PHP/JS/HTML comment):

```
SPDX-FileCopyrightText: <year> Nextcloud GmbH and Nextcloud contributors
SPDX-License-Identifier: AGPL-3.0-or-later
```

Bulk entries for files that can't carry inline headers go in `REUSE.toml`. The `reuse.yml`
CI workflow enforces compliance on every PR.
