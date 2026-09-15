/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createVersionComparisonAccount } from '../../playwright/support/helpers/versionComparisonFixtures.ts'

const HISTORICAL_SNAPSHOT_URL = /\/remote\.php\/dav\/versions\/(?!.*[?&]timestamp=\d{13}(?:&|$))/
const CURRENT_SNAPSHOT_URL = /\/remote\.php\/dav\/versions\/.*[?&]timestamp=\d{13}(?:&|$)/
const SEMANTIC_E2E = Cypress.expose('semanticE2E') === true || Cypress.expose('semanticE2E') === '1'
const describeSemantic = SEMANTIC_E2E ? describe : () => {}
const RUN_NAMESPACE = crypto.randomUUID().slice(0, 8)
const fixtureName = (name) => `c599-e2e-${RUN_NAMESPACE}-${name}`
const COLLECTIVE_NAME = fixtureName('versions')
const PAGE_NAME = fixtureName('page')
const FRESH_PAGE_NAME = fixtureName('fresh-page')
const STABLE_PAGE_NAME = fixtureName('stable-link-page')
const REMOVED_VERSION_PAGE_NAME = fixtureName('removed-version-page')
const SINGLE_REMOVED_VERSION_PAGE_NAME = fixtureName('single-removed-version-page')
const VIEWER_FALLBACK_PAGE_NAME = fixtureName('viewer-fallback-page')
const RAPID_COMPARE_PAGE_NAME = fixtureName('rapid-compare-page')
const RAPID_GENERATION_PAGE_NAME = fixtureName('rapid-generation-page')
const RESTORE_AUTH_PAGE_NAME = fixtureName('restore-auth-page')
const OWNER = createVersionComparisonAccount('owner', `cypress-${RUN_NAMESPACE}`, () => crypto.randomUUID())
const READER = createVersionComparisonAccount('reader', `cypress-${RUN_NAMESPACE}`, () => crypto.randomUUID())
const READER_USER = READER.userId
const OCS_HEADERS = { 'OCS-APIRequest': 'true' }

function provisioningApiUrl(userId = '') {
	const baseUrl = Cypress.config('baseUrl').replace(/\/index\.php\/?$/, '')
	const userPath = userId ? `/${encodeURIComponent(userId)}` : ''
	return `${baseUrl}/ocs/v2.php/cloud/users${userPath}`
}

function provisionTestUser(user) {
	return deleteTestUser(user, false).then(() => cy.request({
		method: 'POST',
		url: provisioningApiUrl(),
		auth: { username: 'admin', password: 'admin' },
		headers: OCS_HEADERS,
		body: {
			userid: user.userId,
			password: user.password,
			language: user.language,
		},
		form: true,
		log: false,
	}))
}

function deleteTestUser(user, failOnStatusCode = true) {
	return cy.clearCookies({ log: false }).then(() => cy.request({
		method: 'DELETE',
		url: provisioningApiUrl(user.userId),
		auth: { username: 'admin', password: 'admin' },
		headers: OCS_HEADERS,
		failOnStatusCode,
		log: false,
	}))
}

function logoutAndClearSession() {
	cy.logout()
	return cy.then(() => Cypress.session.clearAllSavedSessions())
}

function selectVersionAt(selectorIndex, optionIndex, options = {}) {
	cy.get('.version-comparison-dialog select').eq(selectorIndex).then(($select) => {
		cy.wrap($select).select($select.find('option').eq(optionIndex).val(), options)
	})
}

function getVersionComparisonModal() {
	return cy.get('.modal-container').filter(':has(.version-comparison-dialog)')
}

function closeSemanticComparison() {
	getVersionComparisonModal()
		.parents('.modal-mask')
		.should('have.css', 'opacity', '1')
	getVersionComparisonModal().find('button.modal-container__close').click()
	cy.get('.version-comparison-dialog').should('not.exist')
}

function openVersionsSidebar() {
	cy.get('body').should(($body) => {
		expect(
			$body.find('#tab-button-versions:visible, button.page-sidebar-button:visible').length,
			'visible Versions tab or sidebar button',
		).to.be.greaterThan(0)
	}).then(($body) => {
		if (!$body.find('#tab-button-versions:visible').length) {
			cy.get('button.page-sidebar-button:visible').click()
		}
	})
	cy.get('#tab-button-versions').should('be.visible').click()
}

function closeViewerComparison() {
	cy.window().then((window) => window.OCA.Viewer.close())
	cy.get('#viewer').should('not.exist')
}

const ROLLBACK_SECTION = `## Rollback ownership

Maya owns rollback approval.

Escalate checkout regressions to the release commander.`

const EVIDENCE_SECTION = `## Audit archive

Each decision receives a durable timestamp.

Signed records remain with the project history.`

const READINESS_SECTION = `## Regional readiness

All checkout regions use the same health gate.

Regional owners confirm capacity before launch.`

const INITIAL_CONTENT = `---
release: atlas-2.4
status: draft
owner: Maya Chen
---

# Atlas 2.4 release plan

> Draft rollout window: Thursday, 18 July.

## Objective

Ship **Atlas 2.4** to a 10% pilot cohort while protecting checkout availability.

Release owner: Maya Chen.

Status cadence: every 15 minutes.

All named owners have acknowledged the window.

Status dashboard is available to the release team.

The dashboard is reviewed before each traffic increase.

Keep the [incident channel](https://chat.example.test/atlas) staffed during rollout.

The incident commander acknowledges every escalation.

Follow the [operator runbook](https://docs.example.test/atlas/draft).

The release commander validates every operator handoff.

Customer update is ready for review.

Checkout remains available throughout the launch.

${ROLLBACK_SECTION}

${EVIDENCE_SECTION}

${READINESS_SECTION}

## Decision protocol

The release commander records every traffic decision.

## Rollout checklist

- [x] Freeze schema changes
- [ ] Confirm support coverage
- [ ] Enable the pilot cohort

## Schedule

| Stage | Owner | Traffic |
| --- | --- | --- |
| Pilot | Maya | 10% |
| General availability | Lee | Pending |

Checkout remains available throughout the launch.

::: info
The checkout health gate must remain green for fifteen minutes.
:::

Health checks are sampled from every checkout region.

<details>
<summary>Escalation contacts</summary>
Maya leads release decisions; Noor leads rollback execution.
</details>

## Guardrail

\`\`\`js
const rolloutPercent = 10
const rollbackThreshold = 0.02
\`\`\`

Rollback if error rate exceeds \`2%\`.

The rollout decision is recorded in the release evidence.[^atlas-draft]

Visual evidence follows the signed release decision.

![Atlas rollout dashboard](/core/img/logo/logo.svg)

The image checksum is recorded separately.

[^atlas-draft]: Draft approval requires checkout error rate below two percent.
`

const SECOND_CONTENT = INITIAL_CONTENT
	.replace('# Atlas 2.4 release plan', '# Atlas 2.4 release plan #')
	.replace('Status dashboard is available to the release team.', 'Status dashboard is available to the release team. ')
	.replaceAll('\n', '\r\n')
	.replace(/\r\n$/, '')

const REVIEWED_CONTENT = INITIAL_CONTENT
	.replace('status: draft', 'status: reviewed')
	.replace('Draft rollout window', 'Reviewed rollout window')
	.replace('10% pilot cohort', '15% reviewed cohort')
	.replace('- [ ] Confirm support coverage', '- [x] Confirm support coverage')
	.replace('| Pilot | Maya | 10% |', '| Pilot | Maya | 15% |')
	.replace('const rolloutPercent = 10', 'const rolloutPercent = 15')

const CURRENT_CONTENT = `---
release: atlas-2.4
status: launch-ready
owner: Maya Chen and Noor Patel
---

# Atlas 2.4 launch plan

> Approved rollout window: Friday, 19 July.

## Objective

Ship **Atlas 2.4** through a 25% progressive rollout while protecting checkout availability.

Release owner: **Maya Chen**.

Status cadence: *every 15 minutes*.

All named owners have acknowledged the window.

[Status dashboard](https://status.example.test/atlas) is available to the release team.

The dashboard is reviewed before each traffic increase.

Keep the incident channel staffed during rollout.

The incident commander acknowledges every escalation.

Follow the [operator runbook](https://docs.example.test/atlas/2.4).

The release commander validates every operator handoff.

Customer **launch update** is ready for publication.

Checkout remains available throughout the launch.

${EVIDENCE_SECTION}

${READINESS_SECTION}

${ROLLBACK_SECTION}

## Decision protocol

The release commander records every traffic decision.

## Rollout checklist

- [x] Freeze schema changes
- [x] Confirm support coverage
- [x] Enable the pilot cohort
- [ ] Publish the customer update

## Schedule

| Stage | Owner | Traffic |
| --- | --- | --- |
| Canary | Maya | 25% |
| General availability | Lee | 100% |
| Rollback rehearsal | Noor | Complete |

Checkout remains available throughout the launch.

::: warn
The checkout health gate must remain green for fifteen minutes.
:::

Health checks are sampled from every checkout region.

<details>
<summary>Escalation contacts</summary>
Maya leads release decisions; Noor executes rollback and Lee owns customer communication.
</details>

## Guardrail

\`\`\`js
const rolloutPercent = 25
const rollbackThreshold = 0.015
\`\`\`

Rollback if error rate exceeds \`1.5%\` for five minutes.

The rollout decision is recorded in the release evidence.[^atlas-launch]

Visual evidence follows the signed release decision.

![Atlas launch control room](/core/img/logo/logo.svg)

The image checksum is recorded separately.

[^atlas-launch]: Launch approval requires checkout error rate below one and a half percent for five minutes.
`

const INITIAL_PHRASE = '10% pilot cohort'
const CURRENT_PHRASE = '25% progressive rollout'
const STABLE_LINK_CURRENT = 'Stable comparison snapshot'
const RAPID_FIRST_PHRASE = 'Rapid comparison first save'
const RAPID_SECOND_PHRASE = 'Rapid comparison second save'

describeSemantic('Page versions semantic comparison', function() {
	before(function() {
		provisionTestUser(OWNER)
		provisionTestUser(READER)
		cy.login(OWNER)
		cy.deleteAndSeedCollective(COLLECTIVE_NAME)
			.seedPage(PAGE_NAME, '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(FRESH_PAGE_NAME, '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(STABLE_PAGE_NAME, '', 'Readme.md')

		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(REMOVED_VERSION_PAGE_NAME, '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(SINGLE_REMOVED_VERSION_PAGE_NAME, '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(VIEWER_FALLBACK_PAGE_NAME, '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(RAPID_COMPARE_PAGE_NAME, '', 'Readme.md')
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RAPID_COMPARE_PAGE_NAME}.md`, RAPID_FIRST_PHRASE)
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RAPID_COMPARE_PAGE_NAME}.md`, RAPID_SECOND_PHRASE)
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(RAPID_GENERATION_PAGE_NAME, '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.seedPage(RESTORE_AUTH_PAGE_NAME, '', 'Readme.md')
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RESTORE_AUTH_PAGE_NAME}.md`, INITIAL_CONTENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RESTORE_AUTH_PAGE_NAME}.md`, CURRENT_CONTENT)

		// A new version will not be created if the changes occur within less than one second of each other.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${PAGE_NAME}.md`, INITIAL_CONTENT)
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${PAGE_NAME}.md`, SECOND_CONTENT)
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${PAGE_NAME}.md`, REVIEWED_CONTENT)
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${PAGE_NAME}.md`, CURRENT_CONTENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${STABLE_PAGE_NAME}.md`, 'Stable comparison baseline')
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${STABLE_PAGE_NAME}.md`, STABLE_LINK_CURRENT)

		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${REMOVED_VERSION_PAGE_NAME}.md`, INITIAL_CONTENT)
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${REMOVED_VERSION_PAGE_NAME}.md`, CURRENT_CONTENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${SINGLE_REMOVED_VERSION_PAGE_NAME}.md`, INITIAL_CONTENT)
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${SINGLE_REMOVED_VERSION_PAGE_NAME}.md`, CURRENT_CONTENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${VIEWER_FALLBACK_PAGE_NAME}.md`, INITIAL_CONTENT)
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${VIEWER_FALLBACK_PAGE_NAME}.md`, CURRENT_CONTENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RAPID_GENERATION_PAGE_NAME}.md`, INITIAL_CONTENT)
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RAPID_GENERATION_PAGE_NAME}.md`, REVIEWED_CONTENT)
			.wait(1100)
		cy.seedPageContent(`${COLLECTIVE_NAME}/${RAPID_GENERATION_PAGE_NAME}.md`, CURRENT_CONTENT)
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.then(({ circleId }) => cy.wrap({ id: circleId })
				.circleAddMember(READER_USER)
				.circleSetMemberLevel(4))
		cy.seedCollectivePermissions(COLLECTIVE_NAME, 'edit', 8)
	})

	after(function() {
		cy.login(OWNER)
		cy.deleteCollective(COLLECTIVE_NAME)
		deleteTestUser(READER)
		deleteTestUser(OWNER)
	})

	beforeEach(function() {
		cy.login(OWNER)
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}`)
		cy.window().should((window) => {
			expect(typeof window.OCA?.Text?.createMarkdownContentComparison, 'Text semantic comparison factory is available')
				.to.equal('function')
		})

		openVersionsSidebar()
	})

})
