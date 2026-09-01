/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createVersionComparisonAccount } from '../../playwright/support/helpers/versionComparisonFixtures.ts'
import { createCollectiveShare, deleteShare } from '../../src/apis/collectives/shares.js'
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

function insertEditorContent(content, appendParagraph = false) {
	cy.getEditorContent(true).should('be.visible')
	cy.window({ log: false }).then((window) => {
		const components = window.OCA?.Text?.editorComponents
		if (!components) {
			throw new Error('Text editor components are unavailable')
		}
		const component = Array.from(components)
			.find((candidate) => candidate.active)
		if (!component) {
			throw new Error('No active Text editor component is available')
		}
		cy.wrap(component.whenSynced, { log: false }).then(() => {
			const command = appendParagraph
				? component.editor.chain().focus('end').insertContent({
						type: 'paragraph',
						content: [{ type: 'text', text: content }],
					}).run()
				: component.editor.commands.insertContent(content)
			expect(command, 'editor content command').to.equal(true)
		})
	})
}

function assertMeasuredComparisonLayout(expectedMode) {
	cy.get('.version-comparison-dialog .text-comparison').should(($comparison) => {
		const width = $comparison[0].getBoundingClientRect().width
		const measuredMode = width >= 760 ? 'paired' : 'single'
		expect(width, 'measured comparison container width').to.be.greaterThan(0)
		expect($comparison[0].scrollWidth, 'comparison horizontal overflow')
			.to.be.at.most($comparison[0].clientWidth + 1)
		expect($comparison.hasClass(`text-comparison--${measuredMode}`), `layout for ${width}px container`).to.equal(true)
		expect(measuredMode, `expected mode for ${width}px container`).to.equal(expectedMode)
	})
}

let publicSharePath = ''
let publicShare = null

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
		cy.getCollectives()
			.findBy({ name: COLLECTIVE_NAME })
			.then(({ id }) => createCollectiveShare(id).then(({ data }) => {
				const { token } = data.ocs.data
				publicShare = { collectiveId: id, pageId: 0, token }
				publicSharePath = `/apps/collectives/p/${token}/${COLLECTIVE_NAME}/${PAGE_NAME}`
			}))
	})

	after(function() {
		cy.login(OWNER)
		cy.then(() => publicShare && deleteShare(publicShare))
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

	it('C01 compares a historical snapshot before the current snapshot', function() {
		let beforeScrollTop
		let afterScrollTop
		const unexpectedFailures = []
		cy.window().then((window) => {
			window.addEventListener('error', ({ message }) => {
				if (!message.includes('ResizeObserver')) {
					unexpectedFailures.push(`page error: ${message}`)
				}
			})
			window.addEventListener('unhandledrejection', ({ reason }) => unexpectedFailures.push(`unhandled rejection: ${String(reason)}`))
			cy.stub(window.console, 'error').callsFake((...args) => unexpectedFailures.push(`console error: ${args.join(' ')}`))
		})
		const recordFailedResponse = (request) => request.continue((response) => {
			response.headers['cache-control'] = 'no-store'
			if (response.statusCode >= 400) {
				unexpectedFailures.push(`${response.statusCode} ${request.url}`)
			}
		})
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, recordFailedResponse)
			.as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL, recordFailedResponse)
			.as('currentSnapshotRequest')
		cy.get('.search-dialog-container').should('not.exist')
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()

		cy.clickMenuButton('Compare with current version')

		getVersionComparisonModal()
			.parent()
			.should('have.class', 'modal-wrapper--full')
		getVersionComparisonModal().find('button[type="submit"]').should('not.exist')
		getVersionComparisonModal().contains('button', 'Copy comparison link').should('be.visible')
		cy.get('.version-comparison-dialog')
			.should('have.css', 'display', 'flex')
			.and('have.css', 'overflow', 'hidden')
		cy.get('.version-comparison-dialog__comparison')
			.should('have.css', 'display', 'flex')
			.and('have.css', 'overflow', 'hidden')
			.then(($host) => {
				const host = $host[0].getBoundingClientRect()
				const content = $host[0].parentElement.getBoundingClientRect()
				expect(Math.abs(host.bottom - content.bottom), 'host fills remaining dialog height')
					.to.be.lessThan(2)
			})
		cy.get('.version-comparison-dialog__selectors .text-comparison').should('not.exist')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Changes')
			.should('have.attr', 'aria-selected', 'true')
		assertMeasuredComparisonLayout('paired')
		cy.get('.version-comparison-dialog .text-comparison__change-list')
			.should('be.visible')
			.and('contain', 'Moved section')
			.and('contain', 'Bold changed')
			.and('contain', 'Italic changed')
			.and('contain', 'Link changed')
			.and('contain', 'Task state changed')
			.and('contain', 'Callout type changed')
			.and('contain', 'Image description changed')
			.and('contain', 'Footnote changed')
		cy.get('.version-comparison-dialog [data-comparison-select]')
			.should(($records) => {
				expect($records.length).to.be.greaterThan(10)
			})
		cy.contains('.version-comparison-dialog [data-comparison-select]', 'Quote changed')
			.should('be.visible')
		cy.get('.version-comparison-dialog .text-comparison__change-list')
			.should('not.contain', 'Unknown change')
		cy.get('.version-comparison-dialog [data-comparison-select]').each(($record) => {
			expect($record.attr('aria-label'), 'accessible change summary').to.match(/\S/)
			expect($record.text().trim(), 'visible change summary').not.to.be.empty
		})
		cy.get('.version-comparison-dialog .text-comparison__change-list [data-comparison-select]')
			.its('length')
			.then((unfilteredCount) => {
				cy.contains('.version-comparison-dialog label', 'Hide formatting-only changes')
					.find('input[type="checkbox"]')
					.check()
				cy.get('.version-comparison-dialog .text-comparison__change-list [data-comparison-select]')
					.should('have.length', unfilteredCount - 2)
				cy.contains('.version-comparison-dialog label', 'Hide formatting-only changes')
					.find('input[type="checkbox"]')
					.uncheck()
			})

		cy.contains('.version-comparison-dialog [data-comparison-select]', 'Moved section')
			.then(($record) => {
				const changeId = $record.attr('data-comparison-select')
				expect(changeId).to.be.a('string').and.not.be.empty
				cy.wrap($record).click()
				cy.wrap($record).should('have.attr', 'aria-current', 'true')
				cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents')
					.should('have.attr', 'aria-selected', 'true')
				cy.get('.version-comparison-dialog .text-comparison__document--before [data-comparison-change].text-comparison-change--current')
					.should('have.class', 'text-comparison-change--current')
				cy.get('.version-comparison-dialog .text-comparison__document--after [data-comparison-change].text-comparison-change--current')
					.should('have.class', 'text-comparison-change--current')
			})
		cy.contains('.version-comparison-dialog [role="tab"]', 'Changes')
			.click()
		cy.contains('.version-comparison-dialog [role="tab"]', 'Changes')
			.should('have.attr', 'aria-selected', 'true')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents')
			.click()
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents')
			.should('have.attr', 'aria-selected', 'true')
		cy.get('.version-comparison-dialog .text-comparison__document-scroller')
			.should('have.length', 2)
		cy.get('.version-comparison-dialog .text-comparison__document--before')
			.should('contain', INITIAL_PHRASE)
			.and('contain', 'Audit archive')
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', CURRENT_PHRASE)
			.and('contain', 'Audit archive')
		cy.get('.version-comparison-dialog .ProseMirror')
			.should('have.length', 2)
			.find('table')
			.should('have.length', 2)
		cy.get('.version-comparison-dialog .ProseMirror').should(($documents) => {
			const placeholderSelector = '.text-comparison-change--empty, [data-comparison-empty], [data-comparison-placeholder]'
			expect($documents.find(placeholderSelector), 'synthetic empty-side nodes').to.have.length(0)
			const structuralParents = $documents.find('tr, tbody, table, ul, ol, li, p, span')
			expect(
				[...structuralParents].some((element) => element.textContent?.includes('•')),
				'synthetic counterpart bullets in structural or inline parents',
			).to.equal(false)
		})
		cy.get('.version-comparison-dialog .text-comparison__document--before .text-comparison__document-scroller')
			.then(($scroller) => {
				$scroller[0].scrollTo({ top: 80, behavior: 'instant' })
				expect($scroller[0].scrollTop).to.be.greaterThan(0)
			})
		cy.get('.version-comparison-dialog .text-comparison__document--after .text-comparison__document-scroller')
			.then(($scroller) => {
				$scroller[0].scrollTo({ top: 240, behavior: 'instant' })
				expect($scroller[0].scrollTop).to.be.greaterThan(80)
			})
		cy.get('.version-comparison-dialog .text-comparison__document--before .text-comparison__document-scroller')
			.then(($scroller) => {
				beforeScrollTop = $scroller[0].scrollTop
			})
		cy.get('.version-comparison-dialog .text-comparison__document--after .text-comparison__document-scroller')
			.then(($scroller) => {
				afterScrollTop = $scroller[0].scrollTop
			})
		cy.contains('.version-comparison-dialog [role="tab"]', 'Changes')
			.click()
		cy.contains('.version-comparison-dialog [role="tab"]', 'Changes')
			.should('have.attr', 'aria-selected', 'true')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents')
			.click()
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents')
			.should('have.attr', 'aria-selected', 'true')
		cy.get('.version-comparison-dialog .text-comparison__document--before .text-comparison__document-scroller')
			.should(($scroller) => {
				expect($scroller[0].scrollTop).to.equal(beforeScrollTop)
			})
		cy.get('.version-comparison-dialog .text-comparison__document--after .text-comparison__document-scroller')
			.should(($scroller) => {
				expect($scroller[0].scrollTop).to.equal(afterScrollTop)
			})
		cy.get('.version-comparison-dialog .text-comparison__document figure[data-component="image-view"][data-attachment-type="image"]')
			.should('have.length', 2)
		cy.get('@historicalSnapshotRequest.all')
			.should('have.length', 1)
			.its('0.request.url')
			.should('not.contain', 'timestamp=')
		cy.get('@currentSnapshotRequest.all').should('have.length', 1)
		cy.contains('.version-comparison-dialog [role="tab"]', 'Markdown source').click()
		cy.get('.version-comparison-dialog .text-source-comparison')
			.should('contain', 'status: draft')
			.and('contain', 'status: launch-ready')
		cy.viewport(768, 900)
		assertMeasuredComparisonLayout('single')

		closeSemanticComparison()
		cy.viewport(1280, 900)

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		assertMeasuredComparisonLayout('paired')
		cy.get('@historicalSnapshotRequest.all').should('have.length', 2)
		cy.get('@currentSnapshotRequest.all').should('have.length', 2)
		closeSemanticComparison()
		cy.get('.search-dialog-container').should('not.exist')
		cy.get('#tab-button-attachments').click()
		cy.get('.app-sidebar-tabs__content').should('contain', 'No attachments')
		cy.then(() => expect(JSON.stringify(unexpectedFailures), 'unexplained comparison failures').to.equal('[]'))
	})

	it('C05 reuses a successful historical snapshot while the dialog remains open', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('cachedHistoricalSnapshot')
		openInitialCurrentSemanticComparison()
		cy.get('@cachedHistoricalSnapshot.all').should('have.length', 1)

		selectVersionAt(0, 2)
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@cachedHistoricalSnapshot.all').should('have.length', 2)

		selectVersionAt(0, 3)
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@cachedHistoricalSnapshot.all').should('have.length', 2)
	})

	it('C06 reloads the current snapshot for every comparison attempt', function() {
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('uncachedCurrentSnapshot')
		openInitialCurrentSemanticComparison()
		cy.get('@uncachedCurrentSnapshot.all').should('have.length', 1)

		selectVersionAt(0, 2)
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@uncachedCurrentSnapshot.all').should('have.length', 2)

		selectVersionAt(0, 3)
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@uncachedCurrentSnapshot.all').should('have.length', 3)
	})

	it('F11 completes comparison with no unexplained browser or snapshot failures', function() {
		const unexpectedFailures = []
		cy.window().then((window) => {
			window.addEventListener('error', ({ message }) => {
				if (!message.includes('ResizeObserver')) {
					unexpectedFailures.push(`page error: ${message}`)
				}
			})
			window.addEventListener('unhandledrejection', ({ reason }) => unexpectedFailures.push(`unhandled rejection: ${String(reason)}`))
			cy.stub(window.console, 'error').callsFake((...args) => unexpectedFailures.push(`console error: ${args.join(' ')}`))
		})
		const recordFailedResponse = (request) => request.continue((response) => {
			if (response.statusCode >= 400) {
				unexpectedFailures.push(`${response.statusCode} ${request.url}`)
			}
		})
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, recordFailedResponse)
		cy.intercept('GET', CURRENT_SNAPSHOT_URL, recordFailedResponse)

		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		closeSemanticComparison()
		cy.then(() => expect(JSON.stringify(unexpectedFailures), 'unexplained comparison failures').to.equal('[]'))
	})

	it('C02 loads two immutable historical snapshots', function() {
		cy.contains('button', 'Compare versions…').click()
		selectVersionFromEnd(0, 1)
		selectVersionFromEnd(1, 3)
		getVersionComparisonModal().find('button[type="submit"]').click()

		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document--before')
			.should('contain', INITIAL_PHRASE)
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', REVIEWED_PHRASE)
	})

	it('C03 normalizes reversed selectors without swapping visible labels', function() {
		let expectedEarlierLabel
		let expectedLaterLabel
		cy.contains('button', 'Compare versions…').click()
		selectVersionFromEnd(0, 3)
		selectVersionFromEnd(1, 1)
		cy.get('.version-comparison-dialog select').eq(0).find('option:selected').invoke('text')
			.then((label) => { expectedLaterLabel = label.trim() })
		cy.get('.version-comparison-dialog select').eq(1).find('option:selected').invoke('text')
			.then((label) => { expectedEarlierLabel = label.trim() })
		getVersionComparisonModal().find('button[type="submit"]').click()

		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document--before')
			.should('contain', INITIAL_PHRASE)
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', REVIEWED_PHRASE)
		cy.get('.version-comparison-dialog select').eq(0).should(($select) => {
			expect($select.val()).to.match(/^version:[^/\\]+$/)
			expect($select.find('option:selected').text().trim()).to.equal(expectedEarlierLabel)
		})
		cy.get('.version-comparison-dialog select').eq(1).should(($select) => {
			expect($select.find('option:selected').text().trim()).to.equal(expectedLaterLabel)
		})
	})

	it('shows syntax-only Markdown differences without inventing rendered changes', function() {
		cy.contains('button', 'Compare versions…').click()
		selectVersionFromEnd(0, 1)
		selectVersionFromEnd(1, 2)
		getVersionComparisonModal().find('button[type="submit"]').click()

		cy.get('.version-comparison-dialog .text-comparison')
			.should('contain', 'No rendered differences — Markdown syntax differs.')
			.and('not.contain', 'Moved section')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Markdown source').click()
		cy.get('.version-comparison-dialog .text-source-comparison')
			.should('contain', '# Atlas 2.4 release plan #')
			.and('contain', 'Line endings changed: lf → crlf')
			.and('contain', 'crlf')
			.and('contain', 'No newline at end of file')
		cy.get('.version-comparison-dialog .text-source-comparison__line--added code')
			.should(($lines) => {
				expect([...$lines].some((line) => line.textContent.endsWith(' ')), 'literal trailing space').to.equal(true)
			})
		closeSemanticComparison()
	})

	it('R01 encodes the exact ordered pair in a canonical comparison route', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?view=grid#rollout`)
		openVersionsSidebar()
		openInitialCurrentSemanticComparison()

		cy.get('.version-comparison-dialog select').then(($selects) => {
			cy.location().should((location) => {
				const query = new URLSearchParams(location.search)
				expect(query.get('compareFrom')).to.equal($selects.eq(0).val())
				expect(query.get('compareTo')).to.match(/^current:\d+$/)
				expect(query.get('view')).to.equal('grid')
				expect(location.hash).to.equal('#rollout')
				expect(location.href).not.to.contain('/remote.php/dav')
			})
		})
	})

	it('R02 reload restores the exact comparison pair and view', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?view=grid#rollout`)
		openVersionsSidebar()
		openInitialCurrentSemanticComparison()
		cy.location('href').as('reloadedComparisonUrl')

		cy.reload()

		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@reloadedComparisonUrl').then((comparisonUrl) => {
			cy.location('href').should('eq', comparisonUrl)
		})
	})

	it('R03 Back closes the managed comparison and restores the prior route', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?view=grid#rollout`)
		openVersionsSidebar()
		openInitialCurrentSemanticComparison()

		cy.go('back')

		cy.get('.version-comparison-dialog').should('not.exist')
		cy.location().should((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.be.null
			expect(query.get('compareTo')).to.be.null
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
		})
	})

	it('R05 copied link opens the same pair in a fresh authenticated session', function() {
		cy.stubClipboardAndVisit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?view=grid#rollout`)
		openVersionsSidebar()
		openInitialCurrentSemanticComparison()
		cy.location('href').as('freshSessionComparisonUrl')
		getVersionComparisonModal().contains('button', 'Copy comparison link').click()
		cy.get('@freshSessionComparisonUrl').then((comparisonUrl) => {
			cy.getClipboardText().should('eq', comparisonUrl)
			cy.clearCookies()
			cy.clearLocalStorage()
			cy.login(OWNER)
			cy.visit(comparisonUrl)
		})

		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('@freshSessionComparisonUrl').then((comparisonUrl) => {
			cy.location('href').should('eq', comparisonUrl)
		})
	})

	it('R10 closing a managed route preserves unrelated query and history state', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}?view=grid#rollout`)
		openVersionsSidebar()
		openInitialCurrentSemanticComparison()

		closeSemanticComparison()

		cy.location().should((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.be.null
			expect(query.get('compareTo')).to.be.null
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
		})
		cy.window().should(({ history }) => {
			expect(history.state?.collectivesVersionComparison).not.to.equal(true)
		})
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

	it('C16 does not expose version comparison on public routes', function() {
		logoutAndClearSession()
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('versionRequest')
		cy.visit(`${publicSharePath}?compareFrom=missing-version&compareTo=current&view=grid#rollout`)
		cy.getReadOnlyEditor().should('contain', CURRENT_PHRASE)
		cy.location().should((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.be.null
			expect(query.get('compareTo')).to.be.null
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
		})
		cy.contains('Version comparison is not available for public links.').should('be.visible')
		cy.get('#tab-button-versions').should('not.exist')
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

	it('C04 Blocks comparing a version with itself', function() {
		cy.contains('button', 'Compare versions…').click()
		selectVersionAt(0, 1)
		selectVersionAt(1, 1)
		cy.get('.version-comparison-dialog')
			.should('contain', 'Select two different versions.')
		getVersionComparisonModal().find('button[type="submit"]')
			.should('be.disabled')
	})

	it('Uses an accessible Before and After toggle on mobile', function() {
		cy.viewport(768, 900)
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison')
			.should('have.class', 'text-comparison--single')
		assertMeasuredComparisonLayout('single')
		cy.get('.version-comparison-dialog .text-comparison__documents')
			.should('have.css', 'display', 'flex')
		cy.get('.version-comparison-dialog .text-comparison__document-grid')
			.should('have.css', 'display', 'block')
		cy.get('.version-comparison-dialog [role="tablist"]').should('be.visible')
		cy.get('.version-comparison-dialog [role="tab"]').contains('Before')
			.trigger('keydown', { key: 'ArrowRight' })
		cy.get('.version-comparison-dialog [role="tab"][aria-selected="true"]')
			.should('contain', 'After')
		cy.get('.version-comparison-dialog [role="tabpanel"]:visible')
			.should('contain', CURRENT_PHRASE)
		cy.viewport(320, 900)
		cy.get('.version-comparison-dialog [role="tabpanel"]:visible')
			.should('be.visible')
		cy.get('.version-comparison-dialog .text-comparison')
			.should(($comparison) => {
				expect($comparison[0].scrollWidth).to.be.at.most($comparison[0].clientWidth + 1)
			})
	})

	it('uses the comparison container width independently of the desktop viewport', function() {
		cy.viewport(1440, 900)
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		assertMeasuredComparisonLayout('paired')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document-grid')
			.should('have.css', 'display', 'grid')
		getVersionComparisonModal().then(($modal) => {
			$modal[0].style.width = '700px'
		})
		assertMeasuredComparisonLayout('single')
		getVersionComparisonModal().then(($modal) => {
			$modal[0].style.width = ''
		})
		assertMeasuredComparisonLayout('paired')
	})

	it('AUD-06 uses a callable semantic factory despite an unexpected Text API version', function() {
		cy.window().then((window) => {
			window.OCA.Text.apiVersion = 'unexpected'
		})
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison').should('be.visible')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Markdown source').should('be.visible')
		cy.get('#viewer').should('not.exist')
	})

	it('X03 falls back to Viewer when the advertised semantic factory is missing', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('viewerSnapshotRequest')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${VIEWER_FALLBACK_PAGE_NAME}`)
		cy.switchToEditMode()
		cy.intercept('POST', '**/apps/text/session/*/save').as('viewerPreparationSave')
		const typedBytes = 'No-wait Viewer fallback bytes 7f56c599'
		insertEditorContent(typedBytes, true)
		openVersionsSidebar()
		cy.window().then((window) => {
			window.OCA.Text.apiVersion = '1.5'
			cy.stub(window.OCA.Text, 'createMarkdownContentComparison').value(undefined).as('missingSemanticFactory')
		})
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@viewerPreparationSave')
		cy.get('.version-comparison-dialog').should('not.exist')
		cy.get('#viewer .viewer--split > .viewer__file-wrapper:visible')
			.should('have.length', 2)
			.eq(1)
			.should('contain', typedBytes)
		cy.get('@viewerSnapshotRequest.all').should('have.length', 1)
		closeViewerComparison()
		cy.get('@missingSemanticFactory').then((stub) => stub.restore())
	})

	it('prepares current bytes before Viewer fallback dispatch', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${VIEWER_FALLBACK_PAGE_NAME}`)
		cy.switchToEditMode()
		cy.intercept('POST', '**/apps/text/session/*/save', { statusCode: 500 }).as('failedViewerPreparationSave')
		insertEditorContent('Viewer preparation must fail closed 7f56c599', true)
		openVersionsSidebar()
		cy.window().then((window) => {
			cy.stub(window.OCA.Text, 'createMarkdownContentComparison').value(undefined).as('missingSemanticFactory')
		})
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@failedViewerPreparationSave')
		cy.get('#viewer').should('not.exist')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'Could not save current changes before comparison. Please try again.')
		cy.get('@missingSemanticFactory').then((stub) => stub.restore())
	})

	it('C11 retry clears a removed-version error and publishes fresh comparison state', function() {
		cy.intercept({
			method: 'GET',
			times: 1,
			url: HISTORICAL_SNAPSHOT_URL,
		}, { statusCode: 404 }).as('removedVersion')

		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${REMOVED_VERSION_PAGE_NAME}`)
		openVersionsSidebar()
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@removedVersion')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'One of the selected versions has expired or was removed.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')

		getVersionComparisonModal().contains('button', 'Retry').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
	})

	it('C13 reports one removed version for a single 404 snapshot response', function() {
		cy.intercept({
			method: 'GET',
			times: 1,
			url: HISTORICAL_SNAPSHOT_URL,
		}, { statusCode: 404 }).as('singleRemovedVersion')

		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${SINGLE_REMOVED_VERSION_PAGE_NAME}`)
		openVersionsSidebar()
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@singleRemovedVersion')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'One of the selected versions has expired or was removed.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
	})

	it('C12 Shows a permission error when both snapshots are unavailable', function() {
		cy.get('body').then(($body) => {
			if ($body.find('.version-comparison-dialog').length > 0) {
				closeSemanticComparison()
			}
		})
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, { statusCode: 403 })
			.as('deniedSnapshots')

		cy.contains('button', 'Compare versions…').click()
		selectVersionAt(0, 1)
		selectVersionAt(1, 3)
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@deniedSnapshots')
		cy.wait('@deniedSnapshots')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'You do not have permission to load the selected versions.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
		cy.get('#viewer').should('not.exist')
	})

	it('C12 Shows a permission error when one selected snapshot is unavailable', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, { statusCode: 403 }).as('deniedSnapshot')
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@deniedSnapshot')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'You do not have permission to load one of the selected versions.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
	})

	it('C13 Shows a two-version expiry error for 404 and 410 responses', function() {
		let requestCount = 0
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, (request) => {
			request.reply({ statusCode: requestCount++ === 0 ? 410 : 404 })
		}).as('expiredSnapshots')
		cy.contains('button', 'Compare versions…').click()
		selectVersionAt(0, 1)
		selectVersionAt(1, 3)
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@expiredSnapshots')
		cy.wait('@expiredSnapshots')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'The selected versions have expired or were removed.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
	})

	it('C14 reports a network failure instead of treating it as cancellation', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, (request) => {
			request.destroy()
		}).as('networkFailure')
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@networkFailure')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'Could not load the selected versions because of a network error.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
		cy.get('#viewer').should('not.exist')
	})

	it('C09 cancels a superseded request and prevents stale publication', function() {
		const delayedSnapshot = Promise.withResolvers()
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, (request) => {
			return delayedSnapshot.promise.then(() => request.reply('Delayed snapshot'))
		}).as('delayedSnapshot')
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog__loading').should('be.visible')
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${FRESH_PAGE_NAME}`)
		cy.location('pathname').should('contain', `/${FRESH_PAGE_NAME}`)
		cy.getReadOnlyEditor().should('be.visible')
		cy.then(() => delayedSnapshot.resolve())
		cy.wait('@delayedSnapshot')
		cy.get('.version-comparison-dialog').should('not.exist')
		cy.get('.text-comparison').should('not.exist')
		cy.get('[role="alert"]').should('not.exist')
	})

	it('C10 publishes only the latest generation after rapid pair changes', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${RAPID_GENERATION_PAGE_NAME}`)
		openVersionsSidebar()
		const delayedSnapshot = Promise.withResolvers()
		let delayedFirstSnapshot = false
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, (request) => {
			if (!delayedFirstSnapshot) {
				delayedFirstSnapshot = true
				request.alias = 'supersededSnapshot'
				request.continue(() => delayedSnapshot.promise)
			}
		}).as('rapidHistoricalSnapshots')
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog__loading').should('be.visible')
		selectVersionFromEnd(0, 1, { force: true })
		selectVersionFromEnd(1, 2, { force: true })
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document--before')
			.should('contain', INITIAL_PHRASE)
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', REVIEWED_PHRASE)
		cy.then(() => delayedSnapshot.resolve())
		cy.wait('@supersededSnapshot')
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', REVIEWED_PHRASE)
			.and('not.contain', CURRENT_PHRASE)
	})

	it('Reports semantic comparison initialization failures', function() {
		cy.window().then((window) => {
			cy.stub(window.OCA.Text, 'createMarkdownContentComparison')
				.rejects(new Error('comparison failed'))
		})
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'Could not initialize version comparison.')
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
	})

	it('C15 Compares versions for a read-only member', function() {
		cy.login(READER)
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${PAGE_NAME}`)
		cy.get('button.titleform-button').should('not.exist')
		cy.getReadOnlyEditor().should('contain', CURRENT_PHRASE)
		cy.get('button.page-sidebar-button').click()
		cy.get('#tab-button-versions').click()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.contains('Initial version')
			.closest('.list-item')
			.find('.list-item-content__actions')
			.click()
		cy.contains('button', 'Name this version').should('not.exist')
		cy.contains('button', 'Restore version').should('not.exist')
		cy.contains('button', 'Delete version').should('not.exist')
		cy.clickMenuButton('Compare with current version')

		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
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

	it('C07 saves no-wait editor bytes before fetching a current comparison', function() {
		cy.login(OWNER)
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${RAPID_COMPARE_PAGE_NAME}`)
		cy.switchToEditMode()
		cy.intercept('POST', '**/apps/text/session/*/save').as('comparisonPreparationSave')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('preparedCurrentSnapshot')
		const typedBytes = 'No-wait current bytes 7f56c599'
		insertEditorContent(typedBytes, true)

		openVersionsSidebar()
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@comparisonPreparationSave')
		cy.wait('@preparedCurrentSnapshot')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', typedBytes)
		cy.get('[data-cy-collectives="editor"] .ProseMirror').should('exist')
		cy.location('search').should('match', /compareTo=current(?::|%3A)/)
		cy.reload()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', typedBytes)
		closeSemanticComparison()
	})

	it('C08 prevents snapshot reads and reports an actionable preparation failure', function() {
		cy.visit(`/apps/collectives/${COLLECTIVE_NAME}/${RAPID_COMPARE_PAGE_NAME}`)
		cy.switchToEditMode()
		cy.intercept('POST', '**/apps/text/session/*/save', { statusCode: 500 }).as('failedPreparationSave')
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('currentSnapshotRequest')
		insertEditorContent('Preparation must fail before snapshot reads', true)
		openVersionsSidebar()
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.wait('@failedPreparationSave')
		cy.get('.version-comparison-dialog [role="alert"]')
			.should('contain', 'Could not save current changes before comparison. Please try again.')
		cy.get('@historicalSnapshotRequest.all').should('have.length', 0)
		cy.get('@currentSnapshotRequest.all').should('have.length', 0)
		cy.get('.version-comparison-dialog .text-comparison').should('not.exist')
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
