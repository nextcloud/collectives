/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createCollectiveShare } from '../../src/apis/collectives/shares.js'

const HISTORICAL_SNAPSHOT_URL = /\/remote\.php\/dav\/versions\//
const CURRENT_SNAPSHOT_URL = /\/remote\.php\/dav\/files\/.*[?&]timestamp=\d{13}(?:&|$)/

function selectVersionAt(selectorIndex, optionIndex) {
	cy.get('.version-comparison-dialog select').eq(selectorIndex).then(($select) => {
		cy.wrap($select).select($select.find('option').eq(optionIndex).val())
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

let usesLegacyViewer = false
let publicSharePath = ''

function assertViewerComparison(before, after) {
	cy.get('#viewer .viewer--split > .viewer__file-wrapper:visible')
		.should('have.length', 2)
		.eq(0)
		.should('contain', before)
	cy.get('#viewer .viewer--split > .viewer__file-wrapper:visible')
		.eq(1)
		.should('contain', after)
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

describe('Page versions', function() {
	before(function() {
		cy.env(['ncVersion']).then(({ ncVersion }) => {
			usesLegacyViewer = ncVersion === 'stable34'
		})
		cy.loginAs('bob')
		cy.deleteAndSeedCollective('Versions Collective')
			.seedPage('Page', '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.seedPage('Fresh page', '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.seedPage('Stable link page', '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.seedPage('Restore page', '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.seedPage('Removed version page', '', 'Readme.md')
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.seedPage('Viewer fallback page', '', 'Readme.md')
		// Left empty on purpose: this page's content is written through the
		// editor so the save path itself is exercised, not a seeded write.
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.seedPage('Rapid compare page', '', 'Readme.md')

		// A new version will not be created if the changes occur within less than one second of each other.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Page.md', INITIAL_CONTENT)
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Page.md', SECOND_CONTENT)
			.wait(1100)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Page.md', REVIEWED_CONTENT)
			.wait(1100)
		cy.seedPageContent('Versions Collective/Page.md', CURRENT_CONTENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Stable link page.md', 'Stable comparison baseline')
			.wait(1100)
		cy.seedPageContent('Versions Collective/Stable link page.md', STABLE_LINK_CURRENT)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Restore page.md', INITIAL_CONTENT)
			.wait(1100)
		cy.seedPageContent('Versions Collective/Restore page.md', CURRENT_CONTENT)
		// Keep the removed-version request unique so the browser cache cannot bypass its intercept.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Removed version page.md', INITIAL_CONTENT)
			.wait(1100)
		cy.seedPageContent('Versions Collective/Removed version page.md', CURRENT_CONTENT)
		// Keep the Viewer fallback snapshot unique so its request count cannot come from cache.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.seedPageContent('Versions Collective/Viewer fallback page.md', INITIAL_CONTENT)
			.wait(1100)
		cy.seedPageContent('Versions Collective/Viewer fallback page.md', CURRENT_CONTENT)
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.then(({ circleId }) => cy.wrap({ id: circleId })
				.circleAddMember('alice')
				.circleSetMemberLevel(4))
		cy.seedCollectivePermissions('Versions Collective', 'edit', 8)
		cy.getCollectives()
			.findBy({ name: 'Versions Collective' })
			.then(({ id }) => createCollectiveShare(id))
			.its('data.ocs.data.token')
			.then((token) => {
				publicSharePath = `/apps/collectives/p/${token}/Versions Collective/Page`
			})
	})

	beforeEach(function() {
		cy.loginAs('bob')
		cy.visit('/apps/collectives/Versions Collective/Page')

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
		cy.visit('/apps/collectives/Versions Collective/Fresh page')
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

	it('Compare initial and current version', function() {
		let beforeScrollTop
		let afterScrollTop
		if (usesLegacyViewer) {
			cy.intercept('GET', HISTORICAL_SNAPSHOT_URL)
				.as('viewerSnapshotRequest')
		} else {
			cy.intercept('GET', HISTORICAL_SNAPSHOT_URL)
				.as('historicalSnapshotRequest')
			cy.intercept('GET', CURRENT_SNAPSHOT_URL)
				.as('currentSnapshotRequest')
		}
		cy.get('.search-dialog-container').should('not.exist')
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()

		cy.clickMenuButton('Compare with current version')

		if (usesLegacyViewer) {
			assertViewerComparison(INITIAL_PHRASE, CURRENT_PHRASE)
			cy.get('@viewerSnapshotRequest.all').should('have.length', 1)
			closeViewerComparison()
		} else {
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
				.and('contain', 'Bold added')
				.and('contain', 'Italic added')
				.and('contain', 'Link added')
				.and('contain', 'Link removed')
				.and('contain', 'Link target changed')
				.and('contain', 'Task state changed')
				.and('contain', 'Callout type changed')
				.and('contain', 'Image description changed')
				.and('contain', 'Footnote changed')
			cy.get('.version-comparison-dialog [data-comparison-select]')
				.should(($records) => {
					expect($records.length).to.be.greaterThan(10)
					expect($records.length).to.be.lessThan(35)
				})
			cy.contains('.version-comparison-dialog [data-comparison-select]', 'Quote changed — 3 edits')
				.should('be.visible')
			cy.get('.version-comparison-dialog .text-comparison__change-list')
				.should('not.contain', 'Unknown change')
			cy.get('.version-comparison-dialog [data-comparison-select]').each(($record) => {
				cy.wrap($record)
					.find('.text-comparison__change-label, .text-comparison__change-context, .text-comparison__preview')
					.should('have.length', 3)
			})
			cy.get('.version-comparison-dialog .text-comparison__change-list [data-comparison-select]')
				.its('length')
				.then((unfilteredCount) => {
					cy.contains('.version-comparison-dialog label', 'Hide formatting-only changes')
						.find('input[type="checkbox"]')
						.check()
					cy.contains('.version-comparison-dialog label', 'Hide formatting-only changes')
						.should('contain', '2 hidden')
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
					cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents')
						.should('have.attr', 'aria-selected', 'true')
					cy.get(`.version-comparison-dialog .text-comparison__document--before [data-comparison-change="${changeId}"]`)
						.should('have.class', 'text-comparison-change--current')
					cy.get(`.version-comparison-dialog .text-comparison__document--after [data-comparison-change="${changeId}"]`)
						.should('have.class', 'text-comparison-change--current')
				})
			// Finish target navigation before testing manual scroll persistence.
			cy.contains('.version-comparison-dialog [role="tab"]', 'Changes').click()
			cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
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
			cy.get('.version-comparison-dialog .text-comparison-change--empty[role="note"]')
				.its('length')
				.should('be.greaterThan', 0)
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
			cy.contains('.version-comparison-dialog [role="tab"]', 'Changes').click()
			cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
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
		}

		// Reopening reuses immutable history but constructs a fresh comparison with current content.
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')
		if (usesLegacyViewer) {
			assertViewerComparison(INITIAL_PHRASE, CURRENT_PHRASE)
			closeViewerComparison()
		} else {
			cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
			assertMeasuredComparisonLayout('paired')
			cy.get('@historicalSnapshotRequest.all').should('have.length', 1)
			cy.get('@currentSnapshotRequest.all').should('have.length', 2)
			closeSemanticComparison()
		}
		cy.get('.search-dialog-container').should('not.exist')
		cy.get('#tab-button-attachments').click()
		cy.get('.app-sidebar-tabs__content').should('contain', 'No attachments')
	})

	it('Compares two historical versions and normalizes chronology', function() {
		cy.contains('button', 'Compare versions…').click()
		// Deliberately choose the later snapshot in Earlier and vice versa.
		selectVersionAt(0, 1)
		selectVersionAt(1, 3)
		getVersionComparisonModal().find('button[type="submit"]').click()

		if (usesLegacyViewer) {
			assertViewerComparison(INITIAL_PHRASE, REVIEWED_PHRASE)
			closeViewerComparison()
		} else {
			cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
			cy.get('.version-comparison-dialog .text-comparison__document--before')
				.should('contain', INITIAL_PHRASE)
			cy.get('.version-comparison-dialog .text-comparison__document--after')
				.should('contain', REVIEWED_PHRASE)
			cy.get('.version-comparison-dialog select').eq(0).should(($select) => {
				expect($select.val()).to.match(/^version:[^/\\]+$/)
			})
		}
	})

	it('shows syntax-only Markdown differences without inventing rendered changes', function() {
		cy.contains('button', 'Compare versions…').click()
		selectVersionAt(0, 3)
		selectVersionAt(1, 2)
		getVersionComparisonModal().find('button[type="submit"]').click()

		if (usesLegacyViewer) {
			assertViewerComparison(INITIAL_PHRASE, INITIAL_PHRASE)
			closeViewerComparison()
			return
		}

		cy.get('.version-comparison-dialog .text-comparison')
			.should('contain', 'No rendered differences — Markdown syntax differs.')
			.and('not.contain', 'Moved section')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Markdown source').click()
		cy.get('.version-comparison-dialog .text-source-comparison')
			.should('contain', '# Atlas 2.4 release plan #')
			.and('contain', 'Line endings changed: LF → CRLF')
			.and('contain', 'CRLF')
			.and('contain', 'Trailing whitespace')
			.and('contain', 'No newline at end of file')
		closeSemanticComparison()
	})

	it('routes, copies, and restores semantic comparison history', function() {
		cy.stubClipboardAndVisit('/apps/collectives/Versions Collective/Page?view=grid#rollout')
		openVersionsSidebar()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(3)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')

		if (usesLegacyViewer) {
			assertViewerComparison(INITIAL_PHRASE, CURRENT_PHRASE)
			cy.location().should((location) => {
				const query = new URLSearchParams(location.search)
				expect(query.get('compareFrom')).to.be.null
				expect(query.get('compareTo')).to.be.null
				expect(query.get('view')).to.equal('grid')
				expect(location.hash).to.equal('#rollout')
			})
			closeViewerComparison()
			return
		}

		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		let comparisonUrl
		cy.location().then((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.match(/^version:[^/\\]+$/)
			expect(query.get('compareTo')).to.match(/^current:\d+$/)
			expect(query.get('view')).to.equal('grid')
			expect(location.hash).to.equal('#rollout')
			expect(location.href).not.to.contain('/remote.php/dav')
			comparisonUrl = location.href
		})
		cy.go('back')
		cy.get('.version-comparison-dialog').should('not.exist')
		cy.location('search').should('eq', '?view=grid')
		cy.go('forward')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		getVersionComparisonModal()
			.contains('button', 'Copy comparison link')
			.should('not.be.disabled')
			.click()
		cy.getClipboardText().then((copiedUrl) => {
			expect(copiedUrl).to.equal(comparisonUrl)
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
		cy.then(() => cy.stubClipboardAndVisit(comparisonUrl))
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
		if (usesLegacyViewer) {
			return
		}
		cy.visit('/apps/collectives/Versions Collective/Stable link page')
		openVersionsSidebar()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.eq(1)
			.find('.list-item-content__actions')
			.click()
		cy.clickMenuButton('Compare with current version')
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')

		let comparisonUrl
		cy.location().then((location) => {
			const query = new URLSearchParams(location.search)
			expect(query.get('compareFrom')).to.match(/^version:[^/\\]+$/)
			expect(query.get('compareTo')).to.match(/^current:\d+$/)
			comparisonUrl = location.href
		})
		// A subsequent write must turn the routed Current snapshot into immutable history.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(1100)
		cy.seedPageContent('Versions Collective/Stable link page.md', STABLE_LINK_UPDATED)
		cy.then(() => cy.visit(comparisonUrl))
		cy.get('.version-comparison-dialog .text-comparison__document--after')
			.should('contain', STABLE_LINK_CURRENT)
			.and('not.contain', STABLE_LINK_UPDATED)
	})

	it('keeps an unavailable routed identity visible without requesting a snapshot', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('currentSnapshotRequest')
		cy.visit('/apps/collectives/Versions Collective/Page?compareFrom=missing-version&compareTo=current')
		getVersionComparisonModal()
			.should('contain', 'Unavailable version (missing-version)')
			.and('contain', 'One of the selected versions has expired or was removed.')
		cy.get('@historicalSnapshotRequest.all').should('have.length', 0)
		cy.get('@currentSnapshotRequest.all').should('have.length', 0)
		getVersionComparisonModal().contains('button', 'Retry').should('be.visible')
		closeSemanticComparison()
		cy.location('search').should('eq', '')
	})

	it('quarantines ambiguous DAV identities without disabling valid versions', function() {
		let duplicateVersionId
		cy.intercept('PROPFIND', '**/remote.php/dav/versions/**', (request) => {
			request.continue((response) => {
				const mutated = appendQuarantinedVersionEntries(response.body)
				duplicateVersionId = mutated.duplicateVersionId
				response.body = mutated.body
			})
		}).as('versionsWithQuarantinedEntries')
		cy.visit('/apps/collectives/Versions Collective/Page')
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
			cy.wrap($select).find('option').should('have.length', 4)
			cy.wrap($select).find('option[value="version:current"]').should('have.length', 1)
		})
		getVersionComparisonModal().find('button[type="submit"]').click()
		if (usesLegacyViewer) {
			assertViewerComparison(REVIEWED_PHRASE, CURRENT_PHRASE)
			closeViewerComparison()
		} else {
			cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
			closeSemanticComparison()
		}

		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('historicalSnapshotRequest')
		cy.intercept('GET', CURRENT_SNAPSHOT_URL).as('currentSnapshotRequest')
		cy.then(() => {
			expect(duplicateVersionId).to.be.a('string').and.not.be.empty
			cy.visit(`/apps/collectives/Versions Collective/Page?compareFrom=${encodeURIComponent(`version:${duplicateVersionId}`)}&compareTo=current`)
		})
		getVersionComparisonModal()
			.should('contain', 'Ambiguous version')
			.and('contain', 'The version comparison link is ambiguous and could not be opened.')
			.and('not.contain', duplicateVersionId)
		cy.get('@historicalSnapshotRequest.all').should('have.length', 0)
		cy.get('@currentSnapshotRequest.all').should('have.length', 0)
	})

	it('rejects malformed pair parameters before requesting versions', function() {
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('versionRequest')
		cy.visit('/apps/collectives/Versions Collective/Page?compareFrom=versions%2F1&compareTo=current&view=grid#rollout')
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

	it('does not expose version comparison on public routes', function() {
		if (usesLegacyViewer) {
			// Preview mode no longer creates a legacy editing session that can race logout.
			cy.get('[data-cy-collectives="editor"] .ProseMirror').should('not.exist')
		}
		cy.logout()
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

	it('Blocks comparing a version with itself', function() {
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
		if (usesLegacyViewer) {
			cy.get('.version-comparison-dialog')
				.should('contain', 'Version comparison requires a newer version of the Text app on mobile.')
				.find('[role="tablist"]')
				.should('not.exist')
		} else {
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
			cy.contains('.version-comparison-dialog .text-comparison__navigation button', 'Next')
				.should('be.visible')
				.then(($button) => {
					expect($button[0].getBoundingClientRect().right).to.be.at.most(320)
				})
			cy.get('.version-comparison-dialog .text-comparison')
				.should(($comparison) => {
					expect($comparison[0].scrollWidth).to.be.at.most($comparison[0].clientWidth + 1)
				})
		}
	})

	it('uses the comparison container width independently of the desktop viewport', function() {
		if (usesLegacyViewer) {
			return
		}
		cy.viewport(1440, 900)
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		assertMeasuredComparisonLayout('paired')
		cy.contains('.version-comparison-dialog [role="tab"]', 'Full documents').click()
		cy.get('.version-comparison-dialog .text-comparison__document-grid')
			.should('have.css', 'display', 'grid')
		getVersionComparisonModal().then(($modal) => {
			$modal[0].style.width = '960px'
		})
		assertMeasuredComparisonLayout('paired')
		getVersionComparisonModal().then(($modal) => {
			$modal[0].style.width = ''
		})
		assertMeasuredComparisonLayout('paired')
	})

	it('uses a callable semantic factory despite an unexpected Text API version', function() {
		if (usesLegacyViewer) {
			return
		}
		cy.window().then((window) => {
			window.OCA.Text.apiVersion = 'unexpected'
		})
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		cy.get('#viewer').should('not.exist')
	})

	it('falls back to Viewer when the advertised semantic factory is missing', function() {
		if (usesLegacyViewer) {
			return
		}
		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL).as('viewerSnapshotRequest')
		cy.visit('/apps/collectives/Versions Collective/Viewer fallback page')
		openVersionsSidebar()
		cy.window().then((window) => {
			window.OCA.Text.apiVersion = '1.5'
			cy.stub(window.OCA.Text, 'createMarkdownContentComparison').value(undefined)
		})
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog').should('not.exist')
		assertViewerComparison(INITIAL_PHRASE, CURRENT_PHRASE)
		cy.get('@viewerSnapshotRequest.all').should('have.length', 1)
		closeViewerComparison()
	})

	it('Shows a removed-version error without mounting one side and retries', function() {
		if (usesLegacyViewer) {
			cy.window().its('OCA.Text.createMarkdownContentComparison').should('not.exist')
			return
		}

		cy.intercept({
			method: 'GET',
			times: 1,
			url: HISTORICAL_SNAPSHOT_URL,
		}, { statusCode: 404 }).as('removedVersion')

		cy.visit('/apps/collectives/Versions Collective/Removed version page')
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

	it('Shows a permission error when both snapshots are unavailable', function() {
		if (usesLegacyViewer) {
			cy.window().its('OCA.Text.createMarkdownContentComparison').should('not.exist')
			return
		}

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

	it('Shows a network error without mounting one side', function() {
		if (usesLegacyViewer) {
			cy.window().its('OCA.Text.createMarkdownContentComparison').should('not.exist')
			return
		}

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

	it('cancels a delayed comparison when navigating to another page', function() {
		if (usesLegacyViewer) {
			return
		}

		cy.intercept('GET', HISTORICAL_SNAPSHOT_URL, {
			delay: 1200,
			body: 'Delayed snapshot',
		}).as('delayedSnapshot')
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()
		cy.get('.version-comparison-dialog__loading').should('be.visible')
		cy.contains('.app-content-list-item a', 'Fresh page').click({ force: true })
		cy.location('pathname').should('contain', '/Fresh-page')
		cy.getReadOnlyEditor().should('be.visible')
		// Wait beyond the intercepted response to prove stale completion stays ignored.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(1400)
		cy.get('.version-comparison-dialog').should('not.exist')
		cy.get('.text-comparison').should('not.exist')
	})

	it('Reports semantic comparison initialization failures', function() {
		if (usesLegacyViewer) {
			cy.window().its('OCA.Text.createMarkdownContentComparison').should('not.exist')
			return
		}

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

	it('Compares versions for a read-only member', function() {
		cy.loginAs('alice')
		cy.visit('/apps/collectives/Versions Collective/Page')
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

		if (usesLegacyViewer) {
			assertViewerComparison(INITIAL_PHRASE, CURRENT_PHRASE)
			closeViewerComparison()
		} else {
			cy.get('.version-comparison-dialog .text-comparison__change-list').should('be.visible')
		}
	})

	it('Restores initial version after leaving edit mode during member and editor setup', function() {
		let notifyMemberRequestStarted
		let releaseMemberRequests = false
		const heldMemberRequests = []
		const memberRequestStarted = new Cypress.Promise((resolve) => {
			notifyMemberRequestStarted = resolve
		})
		cy.intercept('GET', '**/circles/circles/*/members?**', (request) => {
			notifyMemberRequestStarted()
			if (releaseMemberRequests) {
				request.continue()
				return
			}
			return new Cypress.Promise((resolve) => {
				heldMemberRequests.push(() => {
					request.continue()
					resolve()
				})
			})
		})
		cy.visit('/apps/collectives/Versions Collective/Restore page')
		let editorSetupStarted
		let editorDestroyed
		let releaseEditorSetup
		cy.window().then((window) => {
			const createEditor = window.OCA.Text.createEditor
			let notifyEditorDestroyed
			editorDestroyed = new Cypress.Promise((resolve) => {
				notifyEditorDestroyed = resolve
			})
			const setupHold = new Cypress.Promise((resolve) => {
				releaseEditorSetup = resolve
			})
			editorSetupStarted = new Cypress.Promise((resolve) => {
				window.OCA.Text.createEditor = async (options) => {
					const editorPromise = createEditor(options)
					resolve()
					await setupHold
					const editor = await editorPromise
					const destroy = editor.destroy.bind(editor)
					editor.destroy = () => {
						try {
							return destroy()
						} finally {
							notifyEditorDestroyed()
						}
					}
					return editor
				}
			})
		})
		cy.get('button.titleform-button').should('contain', 'Edit').click()
		cy.then(() => memberRequestStarted)
		cy.then(() => editorSetupStarted)
		cy.get('button.titleform-button').should('contain', 'Preview').click()
		cy.then(() => releaseEditorSetup())
		cy.then(() => {
			releaseMemberRequests = true
			heldMemberRequests.splice(0).forEach((release) => release())
		})
		cy.then(() => editorDestroyed)
		cy.get('[data-cy-collectives="editor"] .ProseMirror').should('not.exist')

		openVersionsSidebar()
		cy.get('.app-sidebar-tabs__content .version-list .list-item')
			.contains('Initial version')
			.closest('.list-item')
			.find('.list-item-content__actions')
			.click()

		cy.intercept('MOVE', '**/dav/versions/**').as('moveVersion')
		cy.clickMenuButton('Restore version')
		cy.wait('@moveVersion')
		cy.contains('.toast-success', 'Restored').should('be.visible')
		cy.get('.toast-success', { timeout: 15000 }).should('not.exist')

		// NC34 can retain the pre-restore reader response in the browser cache.
		cy.reload(true)
		cy.get('[data-cy-collectives="reader"]', { timeout: 15000 })
			.should('contain', INITIAL_PHRASE)
			.and('not.contain', CURRENT_PHRASE)
	})

	it('compares a version saved moments earlier without a reload', function() {
		cy.visit('/apps/collectives/Versions Collective/Rapid compare page')
		insertEditorContent(RAPID_FIRST_PHRASE)
		// Wait for the existing editor-content mirror before this version-refresh test leaves edit mode.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(250)
		cy.intercept('POST', '**/apps/text/session/*/save').as('firstRapidSave')
		cy.switchToPreviewMode()
		cy.wait('@firstRapidSave')

		// A version is only created when two writes are more than a second apart.
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(1100)

		cy.switchToEditMode()
		insertEditorContent(RAPID_SECOND_PHRASE, true)
		// eslint-disable-next-line cypress/no-unnecessary-waiting
		cy.wait(250)
		cy.intercept('POST', '**/apps/text/session/*/save').as('secondRapidSave')
		cy.switchToPreviewMode()
		cy.wait('@secondRapidSave')

		// No reload between the save and the comparison: the sidebar has to be
		// listing the versions that save actually produced, not the ones from
		// before it.
		openVersionsSidebar()
		cy.contains('button', 'Compare versions…').click()
		getVersionComparisonModal().find('button[type="submit"]').click()

		if (usesLegacyViewer) {
			assertViewerComparison(RAPID_FIRST_PHRASE, RAPID_SECOND_PHRASE)
			closeViewerComparison()
		} else {
			cy.get('.version-comparison-dialog .text-comparison__change-list', { timeout: 15000 })
				.should('be.visible')
			cy.get('.version-comparison-dialog').should('not.contain', 'expired or was removed')
			closeSemanticComparison()
		}
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
