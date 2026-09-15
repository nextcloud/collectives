/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createVersionComparisonAccount } from '../../playwright/support/helpers/versionComparisonFixtures.ts'
import { listVersions } from '../../src/apis/dav/davRequests.js'

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

function authenticatedDavRequest(user, options) {
	return cy.clearCookies({ log: false }).then(() => cy.request({
		...options,
		auth: { username: user.userId, password: user.password },
		log: false,
	}))
}

function versionEntries(body) {
	return (body.match(/<d:response>[\s\S]*?<\/d:response>/g) ?? [])
		.filter((entry) => entry.includes('<d:getcontenttype>text/markdown</d:getcontenttype>'))
		.map((entry) => ({
			href: entry.match(/<d:href>([^<]+)<\/d:href>/)?.[1],
			lastModified: Date.parse(entry.match(/<d:getlastmodified>([^<]+)<\/d:getlastmodified>/)?.[1]),
		}))
		.filter(({ href, lastModified }) => href && Number.isFinite(lastModified))
		.toSorted((first, second) => first.lastModified - second.lastModified)
}

function listDavVersions(user, url) {
	return authenticatedDavRequest(user, {
		method: 'PROPFIND',
		url,
		headers: { Depth: '1', 'Content-Type': 'application/xml' },
		body: listVersions(),
	})
}

function selectVersionAt(selectorIndex, optionIndex, options = {}) {
	cy.get('.version-comparison-dialog select').eq(selectorIndex).then(($select) => {
		cy.wrap($select).select($select.find('option').eq(optionIndex).val(), options)
	})
}

function selectVersionFromEnd(selectorIndex, offset, options = {}) {
	cy.get('.version-comparison-dialog select').eq(selectorIndex).then(($select) => {
		const optionIndex = $select.find('option').length - offset
		cy.wrap($select).select($select.find('option').eq(optionIndex).val(), options)
	})
}

function getVersionComparisonModal() {
	return cy.get('.modal-container').filter(':has(.version-comparison-dialog)')
}

function appendQuarantinedVersionEntries(body) {
	const entries = body.match(/<d:response>[\s\S]*?<\/d:response>/g) ?? []
	const historicalEntries = entries
		.filter((entry) => entry.includes('<d:getcontenttype>text/markdown</d:getcontenttype>'))
		.map((entry) => ({
			entry,
			href: entry.match(/<d:href>([^<]+)<\/d:href>/)?.[1],
			lastModified: Date.parse(entry.match(/<d:getlastmodified>([^<]+)<\/d:getlastmodified>/)?.[1]),
		}))
		.filter(({ href, lastModified }) => href && Number.isFinite(lastModified))
		.toSorted((first, second) => first.lastModified - second.lastModified)
	const { entry: duplicatedEntry, href } = historicalEntries[0] ?? {}
	if (!duplicatedEntry || !href) {
		throw new Error('Could not find a historical DAV version to duplicate')
	}
	const rawVersionId = href.split('/').at(-1)
	const duplicateVersionId = decodeURIComponent(rawVersionId)
	const encodedFirstCharacter = `%${rawVersionId.charCodeAt(0).toString(16).toUpperCase()}`
	const duplicateEntry = duplicatedEntry.replace(
		href,
		`${href.slice(0, -rawVersionId.length)}${encodedFirstCharacter}${rawVersionId.slice(1)}`,
	)
	const reservedEntry = duplicatedEntry.replace(
		/(<d:href>[^<]*\/)[^/<]+(<\/d:href>)/,
		'$1current$2',
	)
	const mutatedBody = body.replace(
		/(<\/(?:d:)?multistatus>)/i,
		`${duplicateEntry}${reservedEntry}$1`,
	)
	if (mutatedBody === body) {
		throw new Error('Could not append quarantined DAV versions')
	}
	return {
		body: mutatedBody,
		duplicateVersionId,
	}
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

function openInitialCurrentSemanticComparison() {
	cy.get('.app-sidebar-tabs__content .version-list .list-item')
		.eq(3)
		.find('.list-item-content__actions')
		.click()
	cy.clickMenuButton('Compare with current version')
	cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
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
const REVIEWED_PHRASE = '15% reviewed cohort'
const CURRENT_PHRASE = '25% progressive rollout'
const STABLE_LINK_CURRENT = 'Stable comparison snapshot'
const STABLE_LINK_UPDATED = 'Later page update'
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

	it('Lists versions', function() {
		cy.getReadOnlyEditor()
			.should('contain', CURRENT_PHRASE)

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('have.length', 4)

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('contain', 'Current version')

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('contain', 'Initial version')
	})

	it('Hides comparison when no historical version exists', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${FRESH_PAGE_NAME}`)
		cy.get('#tab-button-versions').click()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('have.length', 1)
		cy.contains('button', 'Compare versions…').should('not.exist')
	})

	it('distinguishes every version selector option down to the second', function() {
		cy.contains('button', 'Compare versions…').click()
		cy.get('.version-comparison-dialog select').each(($select) => {
			const labels = [...$select[0].options].map(({ text }) => text.trim())
			expect(new Set(labels).size).to.equal(labels.length)
			expect(labels.every((label) => /\d{1,2}:\d{2}:\d{2}/.test(label))).to.equal(true)
		})
	})

	it('Open initial and current version', function() {
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.contains('Initial version')
			.click()

		cy.get('.page-title-container')
			.find('.title-version')
			.should('be.visible')
		cy.getReadOnlyEditor()
			.should('contain', INITIAL_PHRASE)

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.contains('Current version')
			.click()

		cy.get('.page-title-container')
			.find('.title-version')
			.should('not.exist')
		cy.getReadOnlyEditor()
			.should('contain', CURRENT_PHRASE)
	})

	it('Add label to version', function() {
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(1)
			.find('.list-item-content__actions')
			.click()

		cy.clickMenuButton('Name this version')

		cy.get('.version-label-modal input[type="text"]')
			.type('v3{enter}')

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.should('contain', 'v3')
	})







	it('R04 Forward reopens the exact semantic comparison state', function() {
		cy.stubClipboardAndVisit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?view=grid#rollout`)
		openVersionsSidebar()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')

		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.location().then((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.match(/^version:[^/\\]+$/)
			expect(query.get('compareTo')).to.match(/^current:\d+$/)
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
			expect(location.href).not.to.contain('/remote.php/dav')
		})
		cy.location('href').as('comparisonUrl')
		cy.go('back')
		cy.get('.version-comparison-dialog').should('not.exist')
		cy.location('search').should('eq', '?view=grid')
		cy.go('forward')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		getVersionComparisonModal()
			.contains('button', 'Copy comparison link')
			.should('not.be.disabled')
			.click()
		cy.get('@comparisonUrl').then((comparisonUrl) => {
			cy.getClipboardText().should('eq', comparisonUrl)
		})
		cy.contains('.toastify', 'Comparison link copied')
			.find('.toast-close')
			.click()
		cy.get('@clipboardWriteText').then((writeText) => {
			writeText.rejects(new Error('clipboard denied'))
		})
		getVersionComparisonModal().contains('button', 'Copy comparison link').click()
		cy.contains('.toast-error', 'Could not copy the comparison link.')
			.should('be.visible')
			.find('.toast-close')
			.click()
		cy.reload()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@comparisonUrl').then((comparisonUrl) => {
			cy.location('href').should('eq', comparisonUrl)
		})

		closeSemanticComparison()
		cy.location('search').should('eq', '?view=grid')
		cy.go('forward')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		selectVersionAt(0, 2)
		cy.location('search').should('eq', '?view=grid')
		getVersionComparisonModal().should('be.visible')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
		closeSemanticComparison()
		cy.window().should(({ history }) => {
			expect(history.state?.collectivesVersionComparison).not.to.equal(true)
		})

		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('routedCurrentSnapshotRequest')
		cy.get('@comparisonUrl').then((comparisonUrl) => cy.stubClipboardAndVisit(comparisonUrl))
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@routedCurrentSnapshotRequest.all').should('have.length', 1)
		closeSemanticComparison()
		cy.location().should((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.be.null
			expect(query.get('compareTo')).to.be.null
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
		})
	})

	it('keeps a copied Current comparison stable after a later page edit', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${STABLE_PAGE_NAME}`)
		openVersionsSidebar()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(1)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')

		cy.location().then((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.match(/^version:[^/\\]+$/)
			expect(query.get('compareTo')).to.match(/^current:\d+$/)
		})
		cy.location('href').as('stableComparisonUrl')
		closeSemanticComparison()
		cy.switchToEditMode()
		cy.getEditorContent(true).type(`{selectall}${STABLE_LINK_UPDATED}`)
		cy.switchToPreviewMode()
		cy.getReadOnlyEditor().should('contain', STABLE_LINK_UPDATED)
		cy.get('@stableComparisonUrl').then((comparisonUrl) => cy.visit(comparisonUrl))
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', STABLE_LINK_CURRENT)
			.and('not.contain', STABLE_LINK_UPDATED)
	})

	it('R07 keeps an unavailable routed identity visible without requesting a snapshot', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('currentSnapshotRequest')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?compareFrom=missing-version&compareTo=current`)
		getVersionComparisonModal()
			.should('contain', 'Unavailable version (missing-version)')
			.and('contain', 'One of the selected versions has expired or was removed.')
		cy.get('@historicalSnapshotRequest.all').should('have.length', 0)
		cy.get('@currentSnapshotRequest.all').should('have.length', 0)
		getVersionComparisonModal().contains('button', 'Retry').should('be.visible')
		closeSemanticComparison()
		cy.location('search').should('eq', '')
	})

	it('R08 keeps two unavailable routed identities visible without requesting snapshots', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('currentSnapshotRequest')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?compareFrom=missing-one&compareTo=missing-two`)
		getVersionComparisonModal()
			.should('contain', 'Unavailable version (missing-one)')
			.and('contain', 'Unavailable version (missing-two)')
			.and('contain', 'The selected versions have expired or were removed.')
		cy.get('@historicalSnapshotRequest.all').should('have.length', 0)
		cy.get('@currentSnapshotRequest.all').should('have.length', 0)
	})

	it('R09 quarantines ambiguous DAV identities without disabling valid versions', function() {
		let duplicateVersionId
		let expectedOptionCount
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.its('length')
			.then((count) => { expectedOptionCount = count })
		cy.intercept('PROPFIND', '**/remote.php/dav/versions/**', (request) => {
			request.continue((response) => {
				const mutated = appendQuarantinedVersionEntries(response.body)
				duplicateVersionId = mutated.duplicateVersionId
				response.body = mutated.body
			})
		}).as('versionsWithQuarantinedEntries')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}`)
		openVersionsSidebar()
		cy.wait('@versionsWithQuarantinedEntries')
		cy.then(() => {
			cy.get('.app-sidebar-tabs__content .version-list .version')
				.filter((_index, element) => element.dataset.versionId === String(duplicateVersionId))
				.should('have.length', 2)
				.first()
				.find('.list-item-content__actions')
				.click()
			cy.clickMenuButton('Compare with current version')
			cy.contains('.toast-error', 'This page version cannot be used for comparison.')
				.should('be.visible')
				.find('.toast-close')
				.click()
			cy.get('.version-comparison-dialog').should('not.exist')
		})

		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal()
			.find('[role="status"]')
			.filter(':contains("Some page versions could not be used for comparison.")')
			.should('have.length', 1)
		cy.get('.version-comparison-dialog select').each(($select) => {
			cy.wrap($select).find('option').should('have.length', expectedOptionCount)
			cy.wrap($select).find('option[value="version:current"]').should('have.length', 1)
		})
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison').should('be.visible')
		closeSemanticComparison()

		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('currentSnapshotRequest')
		cy.then(() => {
			expect(duplicateVersionId).to.be.a('string').and.not.be.empty
			cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?compareFrom=${encodeURIComponent(`version:${duplicateVersionId}`)}&compareTo=current`)
		})
		getVersionComparisonModal()
			.should('contain', 'Ambiguous version')
			.and('contain', 'The version comparison link is ambiguous and could not be opened.')
			.and('not.contain', duplicateVersionId)
		cy.get('@historicalSnapshotRequest.all').should('have.length', 0)
		cy.get('@currentSnapshotRequest.all').should('have.length', 0)
	})

	it('R09 rejects malformed pair parameters before requesting versions', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('versionRequest')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?compareFrom=versions%2F1&compareTo=current&view=grid#rollout`)
		cy.location().should((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.be.null
			expect(query.get('compareTo')).to.be.null
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
		})
		cy.get('.version-comparison-dialog').should('not.exist')
		cy.get('@versionRequest.all').should('have.length', 0)
	})

	it('F10 denies a direct anonymous historical DAV snapshot read', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('authorizedSnapshotRead')
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')
		cy.wait('@authorizedSnapshotRead').its('request.url').then((snapshotUrl) => {
			closeSemanticComparison()
			logoutAndClearSession()
			cy.request({
				url: snapshotUrl,
				failOnStatusCode: false,
				followRedirect: false,
			}).its('status').should('be.oneOf', [401, 403])
		})
	})
















	it('denies a crafted reader restore and allows the equivalent owner restore', function() {
		cy.login(READER)
		cy.intercept('PROPFIND', '**/remote.php/dav/versions/**').as('readerVersions')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${RESTORE_AUTH_PAGE_NAME}`)
		cy.getReadOnlyEditor().should('contain', CURRENT_PHRASE)
		openVersionsSidebar()
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')

		cy.wait('@readerVersions').then(({ request, response }) => {
			const beforeEntries = versionEntries(response.body)
			expect(beforeEntries.length, 'reader-visible version snapshots').to.be.greaterThan(1)
			const sourceUrl = new URL(beforeEntries[0].href, request.url).href
			const fileId = new URL(sourceUrl).pathname.split('/').at(-2)
			expect(fileId, 'versioned file id').to.not.be.empty
			const collectionUrl = request.url
			const pageUrl = `${Cypress.expose('baseUrl')}/remote.php/webdav/.Collectives/${encodeURIComponent(COLLECTIVE_NAME)}/${encodeURIComponent(RESTORE_AUTH_PAGE_NAME)}.md`
			const destination = `${Cypress.expose('baseUrl')}/remote.php/dav/versions/${encodeURIComponent(READER.userId)}/restore/target`

			return authenticatedDavRequest(READER, { url: pageUrl }).then(({ body: beforeBytes }) => {
				return authenticatedDavRequest(READER, {
					method: 'MOVE',
					url: sourceUrl,
					headers: { Destination: destination },
					failOnStatusCode: false,
				}).then(({ status, body }) => {
					if (status === 500) {
						expect(body).to.contain('<s:exception>OCP\\Files\\NotPermittedException</s:exception>')
						expect(body).to.contain('<s:message>Failed to restore version</s:message>')
					} else {
						expect(status).to.equal(403)
					}
					return authenticatedDavRequest(READER, { url: pageUrl })
				}).then(({ body }) => {
					expect(body).to.equal(beforeBytes)
					return listDavVersions(READER, collectionUrl)
				}).then(({ body }) => {
					expect(versionEntries(body)).to.deep.equal(beforeEntries)
					return { fileId, pageUrl }
				})
			})
		}).then(({ fileId, pageUrl }) => {
			const ownerCollectionUrl = `${Cypress.expose('baseUrl')}/remote.php/dav/versions/${encodeURIComponent(OWNER.userId)}/versions/${fileId}`
			return listDavVersions(OWNER, ownerCollectionUrl).then(({ body }) => {
				const ownerEntries = versionEntries(body)
				expect(ownerEntries.length, 'owner-visible version snapshots').to.be.greaterThan(1)
				const sourceUrl = new URL(ownerEntries[0].href, ownerCollectionUrl).href
				return authenticatedDavRequest(OWNER, { url: sourceUrl }).then(({ body: historicalBytes }) => {
					return authenticatedDavRequest(OWNER, {
						method: 'MOVE',
						url: sourceUrl,
						headers: { Destination: `${Cypress.expose('baseUrl')}/remote.php/dav/versions/${encodeURIComponent(OWNER.userId)}/restore/target` },
					}).then(({ status }) => {
						expect(status).to.be.oneOf([201, 204])
						return authenticatedDavRequest(OWNER, { url: pageUrl })
					}).its('body').should('equal', historicalBytes)
				})
			})
		})
	})



	it('restores the initial version through DAV MOVE', function() {
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()

		cy.intercept('MOVE', '**/dav/versions/**').as('moveVersion')
		cy.clickMenuButton('Restore version')
		cy.wait('@moveVersion').its('response.statusCode').should('be.oneOf', [201, 204])
		cy.get('.toast-success').should('contain', 'Restored')

		cy.request('/csrftoken').then(({ body }) => {
			cy.request({
				url: `${Cypress.expose('baseUrl')}/remote.php/webdav/.Collectives/${encodeURIComponent(COLLECTIVE_NAME)}/${encodeURIComponent(PAGE_NAME)}.md`,
				headers: { requesttoken: body.token },
			}).its('body')
				.should('contain', INITIAL_PHRASE)
				.and('not.contain', CURRENT_PHRASE)
		})
	})

	it('Delete version', function() {
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.then(($versions) => {
				cy.wrap($versions)
					.filter(':not(:first)')
					.first()
					.find('.list-item-content__actions')
					.click()

				cy.intercept('DELETE', '**/dav/versions/**').as('deleteVersion')
				cy.clickMenuButton('Delete version')
				cy.wait('@deleteVersion')

				cy.get('.app-sidebar-tabs__content .version-list .list-item')
					.should('have.length', $versions.length - 1)
			})
	})
})

if (!SEMANTIC_E2E) {
	describe('Page versions Viewer fallback', function() {
		before(function() {
			provisionTestUser(OWNER)
			cy.login(OWNER)
			cy.deleteAndSeedCollective(COLLECTIVE_NAME)
				.seedPage(PAGE_NAME, '', 'Readme.md')
			// eslint-disable-next-line cypress/no-unnecessary-waiting
			cy.seedPageContent(`${COLLECTIVE_NAME}/${PAGE_NAME}.md`, INITIAL_CONTENT)
				.wait(1100)
			cy.seedPageContent(`${COLLECTIVE_NAME}/${PAGE_NAME}.md`, CURRENT_CONTENT)
		})

		after(function() {
			cy.login(OWNER)
			cy.deleteCollective(COLLECTIVE_NAME)
			deleteTestUser(OWNER)
		})

		it('AUD-06 stable branches compare through Viewer without the semantic Text factory', function() {
			cy.login(OWNER)
			cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}`)
			cy.window().then((window) => {
				if (typeof window.OCA?.Text?.createMarkdownContentComparison === 'function') {
					cy.stub(window.OCA.Text, 'createMarkdownContentComparison').value(undefined)
				}
				expect(window.OCA?.Text?.createMarkdownContentComparison).not.to.be.a('function')
				expect(window.OCA?.Viewer?.compare).to.be.a('function')
			})
			openVersionsSidebar()
			cy.contains('.app-sidebar-tabs__content .version-list .list-item', 'Initial version')
				.find('.list-item-content__actions')
				.click()
			cy.clickMenuButton('Compare with current version')

			cy.get('#viewer .viewer--split > .viewer__file-wrapper:visible')
				.should('have.length', 2)
				.first()
				.should('contain', INITIAL_PHRASE)
			cy.get('#viewer .viewer--split > .viewer__file-wrapper:visible')
				.eq(1)
				.should('contain', CURRENT_PHRASE)
			closeViewerComparison()
		})
	})
}
