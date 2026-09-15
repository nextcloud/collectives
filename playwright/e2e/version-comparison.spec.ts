/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { User as Account } from '@nextcloud/e2e-test-server'
import type { Page } from '@playwright/test'
import type { Collective } from '../support/fixtures/Collective.ts'
import type { CollectivePage } from '../support/fixtures/CollectivePage.ts'

import { docker, getContainer, runOcc } from '@nextcloud/e2e-test-server/docker'
import { test as base, expect, mergeTests } from '@playwright/test'
import { randomUUID } from 'node:crypto'
import { test as editorTest } from '../support/fixtures/editor.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { User } from '../support/fixtures/User.ts'
import { CURRENT_CONTENT, CURRENT_PHRASE, INITIAL_CONTENT, INITIAL_PHRASE, REVIEWED_CONTENT, SECOND_CONTENT } from '../support/fixtures/versionComparisonMarkdown.ts'
import { apiUrl, circlesApiUrl, ocsHeaders } from '../support/helpers/urls.ts'
import {
	createVersionComparisonAccount,
	deleteVersionComparisonUser,
	provisionVersionComparisonUser,
} from '../support/helpers/versionComparisonFixtures.ts'

const SNAPSHOT_URL = /\/remote\.php\/dav\/(?:versions|files)\//
const container = process.env.PLAYWRIGHT_NC_CONTAINER
	? docker.getContainer(process.env.PLAYWRIGHT_NC_CONTAINER)
	: getContainer()
const runPersistentStackOcc = (command: string[], options = {}) => runOcc(command, { container, ...options })
const runNamespace = randomUUID().slice(0, 8)

interface ProvisionedFixtures {
	collective: Collective
}

interface ProvisionedWorkerFixtures {
	account: Account
	readerAccount: Account
	user: User
}

function namespacedAccount(prefix: string, workerIndex: number, projectName: string): Account {
	return createVersionComparisonAccount(`${prefix}-${runNamespace}-${projectName.replace(/[^a-z0-9]+/gi, '-').toLowerCase()}`, workerIndex, randomUUID) as Account
}

async function provisionAccount(account: Account) {
	await provisionVersionComparisonUser(account, runPersistentStackOcc)
}

async function deleteAccount(account: Account) {
	await deleteVersionComparisonUser(account.userId, runPersistentStackOcc)
}

const provisionedTest = base.extend<ProvisionedFixtures, ProvisionedWorkerFixtures>({
	// eslint-disable-next-line no-empty-pattern
	account: [async ({}, use, workerInfo) => {
		const account = namespacedAccount('pw-owner', workerInfo.workerIndex, workerInfo.project.name)
		await provisionAccount(account)
		await use(account)
		await deleteAccount(account)
	}, { scope: 'worker' }],
	// eslint-disable-next-line no-empty-pattern
	readerAccount: [async ({}, use, workerInfo) => {
		const account = namespacedAccount('pw-reader', workerInfo.workerIndex, workerInfo.project.name)
		await provisionAccount(account)
		await use(account)
		await deleteAccount(account)
	}, { scope: 'worker' }],
	page: async ({ account, baseURL, browser }, use) => {
		const page = await loginAsUser(browser, baseURL, account)
		await use(page)
		await page.close()
	},
	user: [async ({ account }, use) => {
		await use(new User(account))
	}, { scope: 'worker' }],
	collective: async ({ page, user }, use) => {
		const collective = await user.createCollective({
			name: `c599-e2e-pw-${randomUUID()}`,
		}, page)
		await use(collective)
		await user.deleteCollective({ id: collective.data.id }, page)
	},
})

const test = mergeTests(provisionedTest, editorTest)

async function openVersions(page: Page) {
	const tab = page.locator('#tab-button-versions')
	if (!await tab.isVisible()) {
		await page.locator('button.page-sidebar-button').click()
	}
	await tab.click()
}

async function seedVersionPair(collectivePage: CollectivePage, user: User, page: Page) {
	await collectivePage.setContent({ content: 'Historical comparison bytes', user, page })
	await page.waitForTimeout(1100)
	await collectivePage.setContent({ content: 'Current comparison bytes', user, page })
}

async function seedTwoHistoricalVersions(collectivePage: CollectivePage, user: User, page: Page) {
	await collectivePage.setContent({ content: 'First historical comparison bytes', user, page })
	await page.waitForTimeout(1100)
	await collectivePage.setContent({ content: 'Second historical comparison bytes', user, page })
	await page.waitForTimeout(1100)
	await collectivePage.setContent({ content: 'Current comparison bytes', user, page })
}

async function openSeededVersionSelector(collective: Collective, user: User, page: Page, title: string) {
	const collectivePage = await collective.createPage({ title, user, page })
	await seedVersionPair(collectivePage, user, page)
	await collectivePage.open()
	await openVersions(page)
	const priorUrl = page.url()
	const opener = page.getByRole('button', { name: 'Compare versions…' })
	await opener.click()
	const dialog = page.getByRole('dialog', { name: 'Compare versions' })
	await expect(dialog).toBeVisible()
	await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true)
	return { dialog, opener, priorUrl }
}

async function openSeededComparison(collective: Collective, user: User, page: Page, title: string) {
	const { dialog, opener, priorUrl } = await openSeededVersionSelector(collective, user, page, title)
	await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
	await expect(page.locator('.text-comparison')).toBeVisible()
	return { dialog, opener, priorUrl }
}

async function openRoutedComparison(collective: Collective, user: User, page: Page, title: string) {
	const collectivePage = await collective.createPage({ title, user, page })
	await seedVersionPair(collectivePage, user, page)
	await page.goto(`${collectivePage.getPageUrl()}?view=grid#rollout`)
	await collectivePage.waitForContent()
	await openVersions(page)
	const priorUrl = page.url()
	await page.locator('.version-list .list-item').filter({ hasText: 'Initial version' }).locator('.list-item-content__actions').click()
	await page.getByRole('menuitem', { name: 'Compare with current version', exact: true }).click()
	await expect(page.locator('.text-comparison')).toBeVisible()
	return { priorUrl }
}

function auditComparisonFailures(page: Page) {
	const failures: string[] = []
	let sawEmptyUserStatus = false
	page.on('console', (message) => {
		const text = message.text()
		const isExpectedEmptyUserStatus = text.includes('core: Failed to load user status')
		if (message.type() === 'error' && !isExpectedEmptyUserStatus) {
			failures.push(`console: ${text}`)
		}
	})
	page.on('pageerror', (error) => {
		if (error.message !== 'ResizeObserver loop completed with undelivered notifications.') {
			failures.push(`page: ${error.message}`)
		}
	})
	page.on('requestfailed', (request) => {
		failures.push(`request: ${request.url()} ${request.failure()?.errorText ?? 'failed'}`)
	})
	page.on('response', (response) => {
		if (response.status() === 404 && new URL(response.url()).pathname.endsWith('/apps/user_status/api/v1/user_status')) {
			sawEmptyUserStatus = true
			return
		}
		if (response.status() >= 400) {
			failures.push(`response: ${response.status()} ${response.url()}`)
		}
	})
	return () => expect(failures.filter((failure) => !(
		sawEmptyUserStatus
		&& /^console: Failed to load resource: the server responded with a status of 404(?: \(Not Found\)| \(\))?$/.test(failure)
	))).toEqual([])
}

async function installClipboardCapture(page: Page) {
	await page.evaluate(() => {
		Object.defineProperty(navigator, 'clipboard', {
			configurable: true,
			value: {
				writeText: async (value: string) => sessionStorage.setItem('c599-copied-link', value),
			},
		})
	})
}

async function freshAuthenticatedContext(page: Page, serviceWorkers: 'allow' | 'block' = 'allow') {
	return await page.context().browser()!.newContext({
		baseURL: process.env.baseURL || 'http://localhost:8089/index.php/',
		ignoreHTTPSErrors: true,
		serviceWorkers,
		storageState: await page.context().storageState(),
	})
}

test.describe('Version comparison route and current-byte contract', () => {
	test('AUD-06 scheduled browser uses the compatible Text comparison API', async ({ user, page, collective }, testInfo) => {
		const { dialog } = await openSeededComparison(collective, user, page, 'c599-e2e-text-api-page')
		const initialTab = testInfo.config.metadata.comparisonInitialView === 'documents' ? 'Full documents' : 'Changes'
		await expect(page.getByRole('tab', { name: initialTab, exact: true })).toHaveAttribute('aria-selected', 'true')
		await expect(page.getByRole('tab', { name: 'Changes' })).toBeVisible()
		await expect(page.getByRole('tab', { name: 'Full documents' })).toBeVisible()
		const changesTab = page.locator('.text-comparison .view-tabs').getByRole('tab', { name: 'Changes' })
		await changesTab.click()
		const selectedBorderColor = await changesTab.evaluate((element) => getComputedStyle(element).borderBottomColor)
		await changesTab.hover()
		await expect(changesTab).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
		await expect(changesTab).toHaveCSS('border-bottom-color', selectedBorderColor)
		await expect(changesTab).toHaveCSS('border-radius', '0px')
		await page.keyboard.press('Tab')
		await changesTab.focus()
		await expect(changesTab).toHaveCSS('box-shadow', 'none')
		await expect(changesTab).toHaveCSS('outline-style', 'solid')
		const controls = dialog.locator('.version-comparison-dialog__selectors select, .version-comparison-dialog__selectors .copy')
		const rectangles = await controls.evaluateAll((elements) => elements.map((element) => {
			const rect = element.getBoundingClientRect()
			return { top: rect.top, bottom: rect.bottom }
		}))
		expect(Math.max(...rectangles.map(({ top }) => top)) - Math.min(...rectangles.map(({ top }) => top))).toBeLessThan(1)
		expect(Math.max(...rectangles.map(({ bottom }) => bottom)) - Math.min(...rectangles.map(({ bottom }) => bottom))).toBeLessThan(1)
		await expect(dialog.getByRole('button', { name: 'Copy comparison link' })).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')

		await page.setViewportSize({ width: 620, height: 900 })
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison')).toHaveClass(/text-comparison--single/)
		const documentSideTabs = page.locator('.text-comparison .side-tabs [role="tab"]')
		expect(await documentSideTabs.evaluateAll((elements) => elements.map((element) => getComputedStyle(element).borderRadius))).toEqual(['0px', '0px'])

		await page.getByRole('tab', { name: 'Markdown source' }).click()
		const sourceSideTabs = page.locator('.text-source-comparison__side-tabs [role="tab"]')
		await expect(sourceSideTabs).toHaveCount(2)
		expect(await sourceSideTabs.evaluateAll((elements) => elements.map((element) => getComputedStyle(element).borderRadius))).toEqual(['0px', '0px'])
	})

	test('C01 displays a historical snapshot before the current snapshot', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-current-historical-page')
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--before')).toContainText('Historical comparison bytes')
		await expect(page.locator('.text-comparison__document--after')).toContainText('Current comparison bytes')
	})

	test('C07 sends unsaved editor bytes to the current comparison', async ({ user, page, collective, editor }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-comparison-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		await collectivePage.open()
		await collectivePage.switchMode(true)
		await expect(page.locator('.text-menubar--ready')).toBeVisible()
		editor.setMode(true)
		const ordering: string[] = []
		page.on('response', (response) => {
			if (response.request().method() === 'POST' && /\/apps\/text\/session\/.*\/save/.test(response.url()) && response.ok()) {
				ordering.push('saved')
			}
		})
		page.on('request', (request) => {
			if (request.method() === 'GET' && /\/remote\.php\/dav\/versions\//.test(request.url()) && new URL(request.url()).searchParams.has('timestamp')) {
				ordering.push('current')
			}
		})
		const typedBytes = 'No-wait Playwright bytes 7f56c599'
		await editor.getContent().fill(typedBytes)

		await openVersions(page)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--after')).toContainText(typedBytes)
		await expect(collectivePage.getContent(true)).toBeAttached()
		expect(ordering.indexOf('saved')).toBeGreaterThanOrEqual(0)
		expect(ordering.indexOf('current')).toBeGreaterThan(ordering.indexOf('saved'))
		const comparisonUrl = page.url()
		expect(new URL(comparisonUrl).searchParams.get('compareTo')).toMatch(/^current:\d+$/)
		await page.reload()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--after')).toContainText(typedBytes)
		await expect(page).toHaveURL(comparisonUrl)
		await page.locator('.modal-mask:has(.version-comparison-dialog) button.modal-container__close').click()
		await expect(page.locator('.version-comparison-dialog, .text-comparison-root')).toHaveCount(0)
	})

	test('R01 encodes the exact ordered snapshot pair in the canonical route', async ({ user, page, collective }) => {
		await openRoutedComparison(collective, user, page, 'c599-e2e-canonical-route-page')
		const comparison = new URL(page.url())
		expect(comparison.searchParams.get('compareFrom')).toMatch(/^version:[^/\\]+$/)
		expect(comparison.searchParams.get('compareTo')).toMatch(/^current:\d+$/)
		expect(comparison.searchParams.get('view')).toBe('grid')
		expect(comparison.hash).toBe('#rollout')
		expect(comparison.href).not.toContain('/remote.php/dav')
		const selectors = page.locator('.version-comparison-dialog select')
		await expect(selectors.nth(0)).toHaveValue(comparison.searchParams.get('compareFrom')!)
		await expect(selectors.nth(1)).toHaveValue('current')
	})

	test('R02 reload restores the exact comparison pair and view', async ({ user, page, collective }) => {
		await openRoutedComparison(collective, user, page, 'c599-e2e-reload-route-page')
		await page.getByRole('tab', { name: 'Full documents' }).click()
		const comparisonUrl = page.url()
		const comparison = new URL(comparisonUrl)

		await page.reload()

		await expect(page.locator('.text-comparison')).toBeVisible()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--before')).toContainText('Historical comparison bytes')
		await expect(page.locator('.text-comparison__document--after')).toContainText('Current comparison bytes')
		const restored = new URL(page.url())
		expect(restored.searchParams.get('compareFrom')).toBe(comparison.searchParams.get('compareFrom'))
		expect(restored.searchParams.get('compareTo')).toBe(comparison.searchParams.get('compareTo'))
		await expect(page).toHaveURL(comparisonUrl)
	})

	test('R03 Back closes the managed comparison and restores the prior route', async ({ user, page, collective }) => {
		const { priorUrl } = await openRoutedComparison(collective, user, page, 'c599-e2e-back-route-page')

		await page.goBack()

		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
		await expect(page.locator('.text-comparison-root')).toHaveCount(0)
		await expect(page).toHaveURL(priorUrl)
		const restored = new URL(page.url())
		expect(restored.searchParams.has('compareFrom')).toBe(false)
		expect(restored.searchParams.has('compareTo')).toBe(false)
	})

	test('R04 Forward reopens the exact managed comparison state', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-forward-route-page')
		const comparisonUrl = page.url()

		await page.goBack()
		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
		await page.goForward()

		await expect(page).toHaveURL(comparisonUrl)
		await expect(page.locator('.text-comparison')).toBeVisible()
	})

	test('R05 copied link opens the exact pair after a fresh login', async ({ user, page, collective, account, browser, baseURL }) => {
		await openRoutedComparison(collective, user, page, 'c599-e2e-copied-route-page')
		const comparisonUrl = page.url()

		await installClipboardCapture(page)
		await page.getByRole('button', { name: 'Copy comparison link' }).click()
		const copiedUrl = await page.evaluate(() => sessionStorage.getItem('c599-copied-link'))
		expect(copiedUrl).toBe(comparisonUrl)

		const copiedPage = await loginAsUser(browser, baseURL, account)
		try {
			expect(copiedPage.context()).not.toBe(page.context())
			await copiedPage.goto(copiedUrl!)
			await expect(copiedPage.locator('.text-comparison')).toBeVisible()
			await copiedPage.getByRole('tab', { name: 'Full documents' }).click()
			await expect(copiedPage.locator('.text-comparison__document--before')).toContainText('Historical comparison bytes')
			await expect(copiedPage.locator('.text-comparison__document--after')).toContainText('Current comparison bytes')
			await expect(copiedPage).toHaveURL(comparisonUrl)
		} finally {
			await copiedPage.context().close()
		}
	})

	test('R10 close preserves the prior URL and clears the managed history marker', async ({ user, page, collective }) => {
		const { priorUrl } = await openRoutedComparison(collective, user, page, 'c599-e2e-close-route-page')
		await page.locator('.modal-mask:has(.version-comparison-dialog) button.modal-container__close').click()
		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
		await expect(page.locator('.text-comparison-root')).toHaveCount(0)
		await expect(page).toHaveURL(priorUrl)
		expect(await page.evaluate(() => history.state?.collectivesVersionComparison)).not.toBe(true)
	})

	test('F11 completes comparison with no unexplained browser or network failures', async ({ user, page, collective }) => {
		const assertNoFailures = auditComparisonFailures(page)
		await openSeededComparison(collective, user, page, 'c599-e2e-clean-failures-page')
		await page.waitForLoadState('networkidle')
		await page.locator('.modal-mask:has(.version-comparison-dialog) button.modal-container__close').click()
		await expect(page.locator('.version-comparison-dialog, .text-comparison-root')).toHaveCount(0)
		await page.waitForLoadState('networkidle')

		assertNoFailures()
	})

	test('C02 loads two immutable historical snapshots in chronological panes', async ({ user, page, collective }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-historical-pair-page', user, page })
		await seedTwoHistoricalVersions(collectivePage, user, page)
		await collectivePage.open()
		await openVersions(page)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		const selectors = page.locator('.version-comparison-dialog select')
		await selectors.nth(0).selectOption({ index: 2 })
		await selectors.nth(1).selectOption({ index: 1 })
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--before')).toContainText('First historical comparison bytes')
		await expect(page.locator('.text-comparison__document--after')).toContainText('Second historical comparison bytes')
	})

	test('C15 read-only member compares only permitted snapshots', async ({ readerAccount, user, page, baseURL, browser, collective }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-read-only-comparison-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		const memberResponse = await page.request.post(circlesApiUrl(collective.data.circleId, 'members'), {
			headers: ocsHeaders,
			data: { userId: readerAccount.userId, type: 1 },
			failOnStatusCode: true,
		})
		const memberBody = await memberResponse.json()
		expect(memberBody.ocs.meta.statuscode).toBe(200)
		expect(memberBody.ocs.data).toMatchObject({ userId: readerAccount.userId })
		await page.request.put(circlesApiUrl(collective.data.circleId, 'members', memberBody.ocs.data.id, 'level'), {
			headers: ocsHeaders,
			data: { level: 4 },
			failOnStatusCode: true,
		})
		await page.request.put(apiUrl('v1.0', 'collectives', collective.data.id, 'editLevel'), {
			headers: ocsHeaders,
			data: { level: 8 },
			failOnStatusCode: true,
		})

		const readerPage = await loginAsUser(browser, baseURL, readerAccount)
		const snapshotStatuses: number[] = []
		readerPage.on('response', (response) => {
			if (SNAPSHOT_URL.test(response.url())) {
				snapshotStatuses.push(response.status())
			}
		})
		await readerPage.goto(collectivePage.getPageUrl())
		await expect(readerPage.locator('button.titleform-button')).toHaveCount(0)
		await expect(readerPage.locator('[data-cy-collectives="reader"] .ProseMirror')).toContainText('Current comparison bytes')
		await openVersions(readerPage)
		await readerPage.locator('.version-list .list-item').filter({ hasText: 'Initial version' }).locator('.list-item-content__actions').click()
		for (const name of ['Name this version', 'Restore version', 'Delete version']) {
			await expect(readerPage.getByRole('menuitem', { name, exact: true })).toHaveCount(0)
		}
		await readerPage.getByRole('menuitem', { name: 'Compare with current version', exact: true }).click()
		await expect(readerPage.locator('.text-comparison')).toBeVisible()
		expect(snapshotStatuses.length).toBeGreaterThan(0)
		expect(snapshotStatuses.every((status) => status >= 200 && status < 300)).toBe(true)
		await readerPage.close()
	})

	test('C16 rejects public comparison parameters without snapshot requests', async ({ user, page, collective }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-public-comparison-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		const share = await collective.createShare({ page })
		try {
			const snapshotRequests: string[] = []
			page.on('request', (request) => {
				if (SNAPSHOT_URL.test(request.url())) {
					snapshotRequests.push(request.url())
				}
			})
			await page.goto(`${collectivePage.getPageUrl(share.data.token)}?compareFrom=version:1&compareTo=current:2&view=grid#kept`)
			await expect(page.locator('#tab-button-versions')).toHaveCount(0)
			await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
			await expect(page).toHaveURL(/\?view=grid#kept$/)
			expect(snapshotRequests).toEqual([])
		} finally {
			await share.delete()
		}
	})

	test('C16 anonymous public page explains unavailable comparison without snapshot reads', async ({ user, page, collective, browser, baseURL }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-anonymous-comparison-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		const share = await collective.createShare({ page })
		const anonymous = await browser.newContext({ baseURL, storageState: undefined })
		try {
			expect(await anonymous.cookies()).toEqual([])
			const publicPage = await anonymous.newPage()
			const snapshots: string[] = []
			publicPage.on('request', (request) => {
				if (SNAPSHOT_URL.test(request.url())) {
					snapshots.push(request.url())
				}
			})
			await publicPage.goto(`${collectivePage.getPageUrl(share.data.token)}?compareFrom=missing-version&compareTo=current&view=grid#rollout`)
			await expect(publicPage.locator('[data-cy-collectives="reader"] .ProseMirror')).toContainText('Current comparison bytes')
			await expect(publicPage.locator('.toastify').filter({ hasText: 'Version comparison is not available for public links.' })).toBeVisible()
			await expect(publicPage.locator('#tab-button-versions')).toHaveCount(0)
			await expect(publicPage.locator('.version-comparison-dialog, .text-comparison-root')).toHaveCount(0)
			await expect(publicPage).toHaveURL(/\?view=grid#rollout$/)
			expect(snapshots).toEqual([])
		} finally {
			await anonymous.close()
			await share.delete()
		}
	})

	test('R06 preserves the exact pair while canonicalizing a renamed page path', async ({ user, page, collective }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-route-rename-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		await collectivePage.open()
		await openVersions(page)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(page.locator('.text-comparison')).toBeVisible()
		const beforeRename = new URL(page.url())
		const pair = [beforeRename.searchParams.get('compareFrom'), beforeRename.searchParams.get('compareTo')]

		await page.request.put(apiUrl('v1.0', 'collectives', collective.data.id, 'pages', collectivePage.data.id), {
			headers: ocsHeaders,
			data: { title: 'c599-e2e-renamed-comparison-page' },
			failOnStatusCode: true,
		})
		await page.reload()
		await expect(page).toHaveURL(/c599-e2e-renamed-comparison-page/)
		const afterRename = new URL(page.url())
		expect([afterRename.searchParams.get('compareFrom'), afterRename.searchParams.get('compareTo')]).toEqual(pair)
		await expect(page.locator('.text-comparison')).toBeVisible()
	})

	test('AUD-03 binds displayed current bytes and route identity to the committed snapshot', async ({ user, page, collective, editor }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-committed-generation-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		await collectivePage.open()
		await collectivePage.switchMode(true)
		await expect(page.locator('.text-menubar--ready')).toBeVisible()
		editor.setMode(true)
		const typedBytes = 'Committed generation bytes 7f56c599'
		await editor.getContent().fill(typedBytes)
		const currentSnapshotGets: string[] = []
		page.on('request', (request) => {
			if (request.method() === 'GET'
				&& /\/remote\.php\/dav\/versions\//.test(request.url())
				&& new URL(request.url()).searchParams.has('timestamp')) {
				currentSnapshotGets.push(request.url())
			}
		})

		await openVersions(page)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await page.getByRole('tab', { name: 'Full documents' }).click()

		await expect(page.locator('.text-comparison__document--after')).toContainText(typedBytes)
		await expect.poll(() => currentSnapshotGets.length).toBeGreaterThan(0)
		const currentSnapshotUrl = new URL(currentSnapshotGets[currentSnapshotGets.length - 1])
		const pathParts = currentSnapshotUrl.pathname.split('/')
		const requestedIdentity = decodeURIComponent(pathParts[pathParts.length - 1])
		expect(new URL(page.url()).searchParams.get('compareTo')).toBe(`current:${requestedIdentity}`)
	})

	test('AUD-08 bounds streamed snapshot bodies and does not cache oversized failures', async ({ user, page, collective }) => {
		const oversizedBody = 'x'.repeat(2_000_001)
		let completeHistoricalGets = 0
		const collectivePage = await collective.createPage({ title: 'c599-e2e-stream-limit-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		const context = await freshAuthenticatedContext(page, 'block')
		const boundedPage = await context.newPage()
		await boundedPage.route(/\/remote\.php\/dav\/versions\//, async (route) => {
			const request = route.request()
			const url = new URL(request.url())
			if (request.method() !== 'GET' || url.searchParams.has('timestamp')) {
				await route.continue()
				return
			}
			if (request.headers().range) {
				await route.fulfill({
					body: 'Bounded historical preview',
					headers: { 'Content-Range': 'bytes 0-25/2000001' },
					status: 206,
				})
				return
			}
			completeHistoricalGets += 1
			await route.fulfill({ body: oversizedBody, status: 200 })
		})

		try {
			await boundedPage.goto(collectivePage.getPageUrl())
			await boundedPage.locator('[data-cy-collectives="reader"] .ProseMirror').waitFor({ state: 'visible' })
			await openVersions(boundedPage)
			await boundedPage.getByRole('button', { name: 'Compare versions…' }).click()
			const dialog = boundedPage.getByRole('dialog', { name: 'Compare versions' })
			await expect(dialog).toBeVisible()
			await dialog.getByRole('button', { name: 'Compare', exact: true }).click()

			await expect(dialog.getByRole('alert')).toContainText('too large for complete comparison')
			await expect(dialog.locator('.version-comparison-dialog__preview')).toContainText('Bounded historical preview')
			await expect(dialog.locator('.text-comparison')).toHaveCount(0)
			expect(completeHistoricalGets).toBe(1)

			await dialog.getByRole('button', { name: 'Retry' }).click()
			await expect.poll(() => completeHistoricalGets).toBe(2)
			await expect(dialog.getByRole('alert')).toContainText('too large for complete comparison')
			await expect(dialog.locator('.text-comparison')).toHaveCount(0)
		} finally {
			await context.close()
		}
	})

	test('AUD-15 clears the historical snapshot cache when the dialog closes', async ({ user, page, collective }) => {
		const historicalSnapshotGets: string[] = []
		page.on('request', (request) => {
			const url = new URL(request.url())
			if (request.method() === 'GET'
				&& /\/remote\.php\/dav\/versions\//.test(url.pathname)
				&& !url.searchParams.has('timestamp')) {
				historicalSnapshotGets.push(request.url())
			}
		})
		const { dialog } = await openSeededComparison(collective, user, page, 'c599-e2e-cache-lifecycle-page')
		await expect.poll(() => historicalSnapshotGets.length).toBe(1)

		await dialog.focus()
		await page.keyboard.press('Escape')
		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(page.locator('.text-comparison')).toBeVisible()

		await expect.poll(() => historicalSnapshotGets.length).toBe(2)
	})

	test('AUD-20 rejects current aliases that resolve to the same snapshot before body fetch', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-route-self-pair-page')
		const currentRouteId = new URL(page.url()).searchParams.get('compareTo')
		expect(currentRouteId).toMatch(/^current:[^/\\]+$/)
		await page.getByRole('dialog', { name: 'Compare versions' }).getByRole('button', { name: 'Close' }).click()
		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)

		const snapshotGets: string[] = []
		page.on('request', (request) => {
			if (request.method() === 'GET' && /\/remote\.php\/dav\/versions\//.test(request.url())) {
				snapshotGets.push(request.url())
			}
		})
		const aliasUrl = new URL(page.url())
		aliasUrl.searchParams.set('compareFrom', currentRouteId!)
		aliasUrl.searchParams.set('compareTo', 'current')
		await page.goto(aliasUrl.toString())

		const dialog = page.getByRole('dialog', { name: 'Compare versions' })
		await expect(dialog.getByRole('status')).toContainText('Select two different versions.')
		await expect(dialog.getByRole('alert')).toHaveCount(0)
		await expect(dialog.getByRole('button', { name: 'Retry' })).toHaveCount(0)
		expect(snapshotGets).toEqual([])
	})

	test('AUD-09 settled forward and backward Tab remain inside the comparison dialog', async ({ user, page, collective }) => {
		const { dialog } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-focus-containment-page')
		const focusableSelector = 'a[href], button:not([disabled]), select:not([disabled]), textarea:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])'
		await expect.poll(async () => dialog.evaluate((element, selector) => (
			Array.from(element.querySelectorAll<HTMLElement>(selector))
				.filter((candidate) => candidate.getClientRects().length > 0)
				.length
		), focusableSelector)).toBeGreaterThan(1)

		await dialog.evaluate((element, selector) => {
			const focusable = Array.from(element.querySelectorAll<HTMLElement>(selector))
				.filter((candidate) => candidate.getClientRects().length > 0)
			focusable[focusable.length - 1]?.focus()
		}, focusableSelector)
		await page.keyboard.press('Tab')
		expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true)

		await dialog.evaluate((element, selector) => {
			const focusable = Array.from(element.querySelectorAll<HTMLElement>(selector))
				.filter((candidate) => candidate.getClientRects().length > 0)
			focusable[0]?.focus()
		}, focusableSelector)
		await page.keyboard.press('Shift+Tab')
		expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true)
	})

	test('AUD-09 Escape restores focus to the exact comparison opener', async ({ user, page, collective }) => {
		const { dialog, opener } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-focus-restore-page')

		await dialog.focus()
		await page.keyboard.press('Escape')

		await expect(dialog).toHaveCount(0)
		await expect(opener).toBeFocused()
	})

	test('AUD-21 throwing destroy still clears dialog route cache and restores focus', async ({ user, page, collective }) => {
		const { dialog, opener } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-throwing-destroy-page')
		const historicalSnapshotGets: string[] = []
		page.on('request', (request) => {
			if (request.method() === 'GET' && /\/remote\.php\/dav\/versions\//.test(request.url())) {
				historicalSnapshotGets.push(request.url())
			}
		})
		await page.evaluate(() => {
			const auditWindow = window as typeof window & { __c599DestroyCalls?: number }
			auditWindow.__c599DestroyCalls = 0
			Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', {
				configurable: true,
				value: async ({ el }: { el: HTMLElement }) => {
					const marker = document.createElement('div')
					marker.className = 'c599-throwing-destroy-comparison'
					marker.textContent = 'Comparison ready'
					el.append(marker)
					return {
						destroy() {
							auditWindow.__c599DestroyCalls! += 1
							throw new Error('AUD-21 destroy failure')
						},
					}
				},
			})
		})

		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.locator('.c599-throwing-destroy-comparison')).toBeVisible()
		await expect.poll(() => new URL(page.url()).searchParams.has('compareFrom')).toBe(true)
		expect(historicalSnapshotGets.length).toBeGreaterThan(0)
		const firstFetchCount = historicalSnapshotGets.length

		await dialog.focus()
		await page.keyboard.press('Escape')

		await expect(dialog).toHaveCount(0)
		await expect.poll(() => {
			const url = new URL(page.url())
			return [url.searchParams.get('compareFrom'), url.searchParams.get('compareTo')]
		}).toEqual([null, null])
		await expect(opener).toBeFocused()
		await expect.poll(() => page.evaluate(() => (
			window as typeof window & { __c599DestroyCalls?: number }
		).__c599DestroyCalls)).toBe(1)

		await opener.click()
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.locator('.c599-throwing-destroy-comparison')).toBeVisible()
		await expect.poll(() => historicalSnapshotGets.length).toBeGreaterThan(firstFetchCount)
	})

	test('AUD-21 malformed semantic factory fails closed without publishing content or route', async ({ user, page, collective }) => {
		const { dialog } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-malformed-factory-page')
		await page.evaluate(() => {
			Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', {
				configurable: true,
				value: async ({ el }: { el: HTMLElement }) => {
					const marker = document.createElement('div')
					marker.className = 'c599-malformed-factory-content'
					el.append(marker)
					return {}
				},
			})
		})

		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()

		await expect(dialog.getByRole('alert')).toContainText('Could not initialize version comparison.')
		await expect(dialog.locator('.c599-malformed-factory-content')).toHaveCount(0)
		await expect(dialog.locator('.version-comparison-dialog__comparison')).toBeEmpty()
		const url = new URL(page.url())
		expect([url.searchParams.get('compareFrom'), url.searchParams.get('compareTo')]).toEqual([null, null])
	})

	test('AUD-21 delayed stale factory instance is destroyed exactly once', async ({ user, page, collective }) => {
		const { dialog, opener } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-delayed-stale-factory-page')
		await page.evaluate(() => {
			interface DelayedFactoryState {
				destroyCalls: number
				pending: boolean
				release?: () => void
			}
			const auditWindow = window as typeof window & { __c599DelayedFactory?: DelayedFactoryState }
			const state: DelayedFactoryState = { destroyCalls: 0, pending: false }
			auditWindow.__c599DelayedFactory = state
			Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', {
				configurable: true,
				value: async ({ el }: { el: HTMLElement }) => {
					const marker = document.createElement('div')
					marker.className = 'c599-delayed-factory-content'
					el.append(marker)
					state.pending = true
					await new Promise<void>((resolve) => {
						state.release = resolve
					})
					return {
						destroy() {
							state.destroyCalls += 1
						},
					}
				},
			})
		})

		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect.poll(() => page.evaluate(() => (
			window as typeof window & { __c599DelayedFactory?: { pending: boolean } }
		).__c599DelayedFactory?.pending)).toBe(true)

		await dialog.focus()
		await page.keyboard.press('Escape')
		await expect(dialog).toHaveCount(0)
		await expect(opener).toBeFocused()
		await page.evaluate(() => (
			window as typeof window & { __c599DelayedFactory?: { release?: () => void } }
		).__c599DelayedFactory?.release?.())
		await expect.poll(() => page.evaluate(() => (
			window as typeof window & { __c599DelayedFactory?: { destroyCalls: number } }
		).__c599DelayedFactory?.destroyCalls)).toBe(1)
		await page.waitForTimeout(100)
		expect(await page.evaluate(() => (
			window as typeof window & { __c599DelayedFactory?: { destroyCalls: number } }
		).__c599DelayedFactory?.destroyCalls)).toBe(1)
		await expect(page.locator('.c599-delayed-factory-content')).toHaveCount(0)
	})

	test('X03 uses existing Viewer.compare when the Text semantic factory is absent', { tag: '@viewer-fallback' }, async ({ user, page, collective }) => {
		const assertNoFailures = auditComparisonFailures(page)
		const collectivePage = await collective.createPage({ title: 'c599-e2e-viewer-fallback-page', user, page })
		await seedVersionPair(collectivePage, user, page)
		await collectivePage.open()
		await openVersions(page)
		await page.evaluate(() => {
			Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', {
				configurable: true,
				value: async ({ el }: { el: HTMLElement }) => {
					const marker = document.createElement('div')
					marker.className = 'viewer-fallback-route-source'
					el.append(marker)
					return { destroy: () => marker.remove() }
				},
			})
		})
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(page.locator('.viewer-fallback-route-source')).toHaveCount(1)
		await expect(page).toHaveURL(/compareFrom=/)
		const comparisonUrl = page.url()
		const comparisonPair = new URL(comparisonUrl)
		await page.goBack()
		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
		await page.evaluate(() => {
			Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', {
				configurable: true,
				value: undefined,
			})
		})
		await page.evaluate((url) => {
			window.history.pushState({}, '', url)
			window.dispatchEvent(new PopStateEvent('popstate', { state: window.history.state }))
		}, comparisonUrl)
		const viewerPanes = page.locator('#viewer .viewer--split > .viewer__file-wrapper:visible')
		await expect(viewerPanes).toHaveCount(2)
		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
		const retained = new URL(page.url())
		expect([retained.searchParams.get('compareFrom'), retained.searchParams.get('compareTo')]).toEqual([
			comparisonPair.searchParams.get('compareFrom'),
			comparisonPair.searchParams.get('compareTo'),
		])
		await expect(viewerPanes.nth(0)).toContainText('Historical comparison bytes')
		await expect(viewerPanes.nth(1)).toContainText('Current comparison bytes')
		assertNoFailures()
	})
})

async function openRichVersions(collective: Collective, user: User, page: Page) {
	const collectivePage = await collective.createPage({ title: 'c599-e2e-rich-comparison', user, page })
	for (const [index, content] of [INITIAL_CONTENT, SECOND_CONTENT, REVIEWED_CONTENT, CURRENT_CONTENT].entries()) {
		if (index > 0) {
			await page.waitForTimeout(1100)
		}
		await collectivePage.setContent({ content, user, page })
	}
	await collectivePage.open()
	await openVersions(page)
	await expect(page.locator('.version-list .list-item')).toHaveCount(4)
	return collectivePage
}

async function compareInitialWithCurrent(page: Page) {
	await page.locator('.version-list .list-item').filter({ hasText: 'Initial version' }).locator('.list-item-content__actions').click()
	await page.getByRole('menuitem', { name: 'Compare with current version', exact: true }).click()
	await expect(page.locator('.text-comparison')).toBeVisible()
}

async function closeComparison(page: Page) {
	await page.locator('.modal-mask:has(.version-comparison-dialog) button.modal-container__close').click()
	await expect(page.locator('.version-comparison-dialog, .text-comparison-root')).toHaveCount(0)
}

async function expectComparisonLayout(page: Page, mode: 'single' | 'paired') {
	const comparison = page.locator('.text-comparison')
	await expect(comparison).toHaveClass(new RegExp(`text-comparison--${mode}`))
	const geometry = await comparison.evaluate((element) => ({
		width: element.getBoundingClientRect().width,
		overflow: element.scrollWidth - element.clientWidth,
	}))
	expect(geometry.width).toBeGreaterThan(0)
	expect(geometry.width < 760 ? 'single' : 'paired').toBe(mode)
	expect(geometry.overflow).toBeLessThanOrEqual(1)
}

test.describe('Rich comparison rendering and resources', () => {
	test('C01 full application layout, meaningful summaries and selected highlights', async ({ collective, user, page }) => {
		await openRichVersions(collective, user, page)
		const assertNoFailures = auditComparisonFailures(page)
		await expect(page.locator('.search-dialog-container')).toHaveCount(0)
		await compareInitialWithCurrent(page)
		const dialog = page.locator('.version-comparison-dialog')
		await expect(page.locator('.modal-wrapper--full .version-comparison-dialog')).toBeVisible()
		await expect(dialog.locator('button[type="submit"]')).toHaveCount(0)
		await expect(dialog.getByRole('button', { name: 'Copy comparison link' })).toBeVisible()
		for (const element of [dialog, page.locator('.version-comparison-dialog__comparison')]) {
			await expect(element).toHaveCSS('display', 'flex')
			await expect(element).toHaveCSS('overflow', 'hidden')
		}
		const remainingHeight = await page.locator('.version-comparison-dialog__comparison').evaluate((element) => (
			Math.abs(element.getBoundingClientRect().bottom - element.parentElement!.getBoundingClientRect().bottom)
		))
		expect(remainingHeight).toBeLessThan(2)
		await expect(dialog.locator('.version-comparison-dialog__selectors .text-comparison')).toHaveCount(0)
		await expectComparisonLayout(page, 'paired')
		await page.getByRole('tab', { name: 'Changes', exact: true }).click()
		const records = dialog.locator('[data-comparison-select]')
		const list = dialog.locator('.text-comparison__change-list')
		for (const label of ['Moved section', 'Bold changed', 'Italic changed', 'Link changed', 'Task state changed', 'Callout type changed', 'Image description changed', 'Footnote changed', 'Quote changed']) {
			await expect(list).toContainText(label)
		}
		await expect(list).not.toContainText('Unknown change')
		const count = await records.count()
		expect(count).toBeGreaterThan(10)
		for (const record of await records.all()) {
			await expect(record).toHaveAttribute('aria-label', /\S/)
			await expect(record).toHaveText(/\S/)
		}
		const filter = dialog.getByLabel('Hide formatting-only changes')
		await filter.check()
		await expect(records).toHaveCount(count - 2)
		await filter.uncheck()
		await expect(records).toHaveCount(count)
		const moved = records.filter({ hasText: 'Moved section' }).first()
		await moved.click()
		await expect(moved).toHaveAttribute('aria-current', 'true')
		await expect(page.getByRole('tab', { name: 'Full documents' })).toHaveAttribute('aria-selected', 'true')
		for (const side of ['before', 'after']) {
			const selected = dialog.locator(`.text-comparison__document--${side} [data-comparison-change].text-comparison-change--current`).first()
			await expect(selected).toBeVisible()
			await expect(selected).toHaveCSS('outline-style', 'solid')
			await expect(selected).toHaveCSS('outline-width', '2px')
		}
		await closeComparison(page)
		await page.waitForLoadState('networkidle')
		assertNoFailures()
	})

	test('C01 original documents, independent pane offsets and editor identity survive view switches', async ({ collective, user, page }) => {
		await openRichVersions(collective, user, page)
		await compareInitialWithCurrent(page)
		await page.getByRole('tab', { name: 'Changes', exact: true }).click()
		await page.locator('[data-comparison-select]').filter({ hasText: 'Moved section' }).first().click()
		const before = page.locator('.text-comparison__document--before')
		const after = page.locator('.text-comparison__document--after')
		await expect(before).toContainText(INITIAL_PHRASE)
		await expect(after).toContainText(CURRENT_PHRASE)
		for (const side of [before, after]) {
			await expect(side).toContainText('Audit archive')
		}
		const editors = page.locator('.version-comparison-dialog .ProseMirror')
		await expect(editors).toHaveCount(2)
		await expect(editors.locator('table')).toHaveCount(2)
		await expect(before.locator('table tr')).toHaveCount(3)
		await expect(after.locator('table tr')).toHaveCount(4)
		await expect(before.locator('table')).toContainText('Pilot')
		await expect(after.locator('table')).toContainText('Rollback rehearsal')
		await expect(editors.locator('.text-comparison-change--empty, [data-comparison-empty], [data-comparison-placeholder]')).toHaveCount(0)
		expect(await editors.locator('tr, tbody, table, ul, ol, li, p, span').evaluateAll((elements) => elements.some((element) => element.textContent?.includes('•')))).toBe(false)
		const handles = await editors.elementHandles()
		const scrollers = page.locator('.text-comparison__document-scroller')
		await expect(scrollers).toHaveCount(2)
		await scrollers.nth(0).evaluate((element) => element.scrollTo({ top: 80, behavior: 'instant' }))
		await scrollers.nth(1).evaluate((element) => element.scrollTo({ top: 240, behavior: 'instant' }))
		const offsets = await scrollers.evaluateAll((elements) => elements.map((element) => element.scrollTop))
		expect(offsets[0]).toBeGreaterThan(0)
		expect(offsets[1]).toBeGreaterThan(80)
		expect(offsets[0]).not.toBe(offsets[1])
		await page.getByRole('tab', { name: 'Changes', exact: true }).click()
		await expect(page.getByRole('tab', { name: 'Changes', exact: true })).toHaveAttribute('aria-selected', 'true')
		await page.getByRole('tab', { name: 'Full documents' }).click()
		expect(await scrollers.evaluateAll((elements) => elements.map((element) => element.scrollTop))).toEqual(offsets)
		for (const handle of handles) {
			expect(await handle.evaluate((element) => element.isConnected)).toBe(true)
			await handle.dispose()
		}
		await closeComparison(page)
	})

	test('C01 resources, literal Source and fresh requests across close and reopen', async ({ collective, user, page }) => {
		await openRichVersions(collective, user, page)
		const assertNoFailures = auditComparisonFailures(page)
		const historical: string[] = []
		const current: string[] = []
		await page.route(/\/remote\.php\/dav\/versions\//, async (route) => {
			if (route.request().method() !== 'GET') {
				await route.continue()
				return
			}
			const url = new URL(route.request().url())
			;(url.searchParams.has('timestamp') ? current : historical).push(url.href)
			const response = await route.fetch()
			await route.fulfill({ response, headers: { ...response.headers(), 'cache-control': 'no-store' } })
		})
		await compareInitialWithCurrent(page)
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document figure[data-component="image-view"][data-attachment-type="image"]')).toHaveCount(2)
		expect(historical).toHaveLength(1)
		expect(historical[0]).not.toContain('timestamp=')
		expect(current).toHaveLength(1)
		await page.getByRole('tab', { name: 'Markdown source' }).click()
		await expect(page.locator('.text-source-comparison')).toContainText('status: draft')
		await expect(page.locator('.text-source-comparison')).toContainText('status: launch-ready')
		await page.setViewportSize({ width: 768, height: 900 })
		await expectComparisonLayout(page, 'single')
		await closeComparison(page)
		await page.setViewportSize({ width: 1280, height: 900 })
		await compareInitialWithCurrent(page)
		await expectComparisonLayout(page, 'paired')
		expect(historical).toHaveLength(2)
		expect(current).toHaveLength(2)
		await closeComparison(page)
		await expect(page.locator('.search-dialog-container')).toHaveCount(0)
		await page.locator('#tab-button-attachments').click()
		await expect(page.locator('.app-sidebar-tabs__content')).toContainText('No attachments')
		await page.waitForLoadState('networkidle')
		assertNoFailures()
	})

	test('Syntax-only differences preserve literal Markdown and line endings in Source', async ({ collective, user, page }) => {
		await openRichVersions(collective, user, page)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		const selectors = page.locator('.version-comparison-dialog select')
		await selectors.nth(0).selectOption({ index: 3 })
		await selectors.nth(1).selectOption({ index: 2 })
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(page.locator('.text-comparison')).toContainText('No rendered differences — Markdown syntax differs.')
		await expect(page.locator('.text-comparison')).not.toContainText('Moved section')
		await page.getByRole('tab', { name: 'Markdown source' }).click()
		const source = page.locator('.text-source-comparison')
		for (const text of ['# Atlas 2.4 release plan #', 'Line endings changed: lf → crlf', 'crlf', 'No newline at end of file']) {
			await expect(source).toContainText(text)
		}
		expect(await source.locator('.text-source-comparison__line--added code').evaluateAll((lines) => lines.some((line) => line.textContent!.endsWith(' ')))).toBe(true)
		await closeComparison(page)
	})

	test('Document side keyboard controls and overflow work at mobile widths', async ({ collective, user, page }) => {
		await openRichVersions(collective, user, page)
		await page.setViewportSize({ width: 768, height: 900 })
		await compareInitialWithCurrent(page)
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expectComparisonLayout(page, 'single')
		await expect(page.locator('.text-comparison__documents')).toHaveCSS('display', 'flex')
		await expect(page.locator('.text-comparison__document-grid')).toHaveCSS('display', 'block')
		const tabs = page.locator('.text-comparison .side-tabs')
		await tabs.getByRole('tab', { name: 'Before' }).press('ArrowRight')
		await expect(tabs.getByRole('tab', { name: 'After' })).toHaveAttribute('aria-selected', 'true')
		await expect(page.locator('.text-comparison__document--after')).toBeVisible()
		await expect(page.locator('.text-comparison__document--after')).toContainText(CURRENT_PHRASE)
		await page.setViewportSize({ width: 320, height: 900 })
		await expectComparisonLayout(page, 'single')
		await expect(page.locator('.text-comparison__document--after')).toBeVisible()
		await closeComparison(page)
	})

	test('Document layout follows the comparison container independently of desktop width', async ({ collective, user, page }) => {
		await page.setViewportSize({ width: 1440, height: 900 })
		await openRichVersions(collective, user, page)
		await compareInitialWithCurrent(page)
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expectComparisonLayout(page, 'paired')
		await expect(page.locator('.text-comparison__document-grid')).toHaveCSS('display', 'grid')
		const modal = page.locator('.modal-container:has(.version-comparison-dialog)')
		await modal.evaluate((element: HTMLElement) => {
			element.style.width = '700px'
		})
		await expectComparisonLayout(page, 'single')
		await modal.evaluate((element: HTMLElement) => {
			element.style.width = ''
		})
		await expectComparisonLayout(page, 'paired')
		await closeComparison(page)
	})
})

async function openTwoHistoricalSelector(collective: Collective, user: User, page: Page) {
	const collectivePage = await collective.createPage({ title: 'c599-e2e-lifecycle', user, page })
	await seedTwoHistoricalVersions(collectivePage, user, page)
	await collectivePage.open()
	await openVersions(page)
	await page.getByRole('button', { name: 'Compare versions…' }).click()
	return page.getByRole('dialog', { name: 'Compare versions' })
}

function countSnapshotReads(page: Page) {
	const reads = { historical: [] as string[], current: [] as string[] }
	page.on('request', (request) => {
		if (request.method() === 'GET' && /\/remote\.php\/dav\/versions\//.test(request.url())) {
			const url = new URL(request.url())
			reads[url.searchParams.has('timestamp') ? 'current' : 'historical'].push(url.href)
		}
	})
	return reads
}

test.describe('Comparison selection, caching and failure lifecycle', () => {
	test('C03 reversed historical selectors normalize chronology and visible labels', async ({ collective, user, page }) => {
		const dialog = await openTwoHistoricalSelector(collective, user, page)
		const selectors = dialog.locator('select')
		await selectors.nth(0).selectOption({ index: 1 })
		await selectors.nth(1).selectOption({ index: 2 })
		const laterLabel = await selectors.nth(0).locator('option:checked').textContent()
		const earlierLabel = await selectors.nth(1).locator('option:checked').textContent()
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--before')).toContainText('First historical comparison bytes')
		await expect(page.locator('.text-comparison__document--after')).toContainText('Second historical comparison bytes')
		await expect(selectors.nth(0)).toHaveValue(/^version:[^/\\]+$/)
		await expect(selectors.nth(0).locator('option:checked')).toHaveText(earlierLabel!)
		await expect(selectors.nth(1).locator('option:checked')).toHaveText(laterLabel!)
	})

	test('C04 identical selectors disable comparison without snapshot reads', async ({ collective, user, page }) => {
		const dialog = await openTwoHistoricalSelector(collective, user, page)
		const reads = countSnapshotReads(page)
		await dialog.locator('select').nth(0).selectOption({ index: 1 })
		await dialog.locator('select').nth(1).selectOption({ index: 1 })
		await expect(dialog).toContainText('Select two different versions.')
		await expect(dialog.getByRole('button', { name: 'Compare', exact: true })).toBeDisabled()
		expect(reads).toEqual({ historical: [], current: [] })
	})

	test('C05 C06 historical bytes cache within a dialog while each current comparison reads fresh bytes', async ({ collective, user, page }) => {
		const dialog = await openTwoHistoricalSelector(collective, user, page)
		const reads = countSnapshotReads(page)
		for (const [attempt, index] of [2, 1, 2].entries()) {
			await dialog.locator('select').nth(0).selectOption({ index })
			await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
			await expect(dialog.locator('.text-comparison')).toBeVisible()
			expect(reads.historical).toHaveLength(Math.min(attempt + 1, 2))
			expect(reads.current).toHaveLength(attempt + 1)
		}
	})

	for (const scenario of [
		{ title: 'C13 one missing historical snapshot', statuses: [404], message: 'One of the selected versions has expired or was removed.' },
		{ title: 'C12 both historical snapshots denied', statuses: [403, 403], message: 'You do not have permission to load the selected versions.' },
		{ title: 'C12 one historical snapshot denied', statuses: [403], message: 'You do not have permission to load one of the selected versions.' },
		{ title: 'C13 two historical snapshots expired with 410 and 404', statuses: [410, 404], message: 'The selected versions have expired or were removed.' },
		{ title: 'C14 network failure is reported rather than treated as cancellation', statuses: [0], message: 'Could not load the selected versions because of a network error.' },
	]) {
		test(scenario.title, async ({ collective, user, page }) => {
			const dialog = await openTwoHistoricalSelector(collective, user, page)
			if (scenario.statuses.length === 2) {
				await dialog.locator('select').nth(0).selectOption({ index: 2 })
				await dialog.locator('select').nth(1).selectOption({ index: 1 })
			}
			let requests = 0
			await page.route(/\/remote\.php\/dav\/versions\//, async (route) => {
				if (route.request().method() !== 'GET' || new URL(route.request().url()).searchParams.has('timestamp')) {
					await route.continue()
					return
				}
				const status = scenario.statuses[Math.min(requests++, scenario.statuses.length - 1)]
				if (status === 0) {
					await route.abort('failed')
				} else {
					await route.fulfill({ status, body: '' })
				}
			})
			await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
			await expect(dialog.getByRole('alert')).toContainText(scenario.message)
			expect(requests).toBe(scenario.statuses.length)
			await expect(dialog.locator('.text-comparison')).toHaveCount(0)
			await expect(page.locator('#viewer')).toHaveCount(0)
		})
	}

	test('C11 retry after a removed version publishes a fresh result and clears the error', async ({ collective, user, page }) => {
		const { dialog } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-retry')
		let historicalReads = 0
		await page.route(/\/remote\.php\/dav\/versions\//, async (route) => {
			if (route.request().method() === 'GET' && !new URL(route.request().url()).searchParams.has('timestamp') && historicalReads++ === 0) {
				await route.fulfill({ status: 404, body: '' })
			} else {
				await route.continue()
			}
		})
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.getByRole('alert')).toContainText('One of the selected versions has expired or was removed.')
		await expect(dialog.locator('.text-comparison')).toHaveCount(0)
		await dialog.getByRole('button', { name: 'Retry' }).click()
		await expect(dialog.locator('.text-comparison')).toBeVisible()
		await expect(dialog.getByRole('alert')).toHaveCount(0)
		expect(historicalReads).toBe(2)
	})

	test('Semantic factory rejection clears result and reports initialization failure', async ({ collective, user, page }) => {
		const { dialog } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-factory-reject')
		await page.evaluate(() => {
			Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', {
				configurable: true,
				value: async () => {
					throw new Error('comparison failed')
				},
			})
		})
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.getByRole('alert')).toContainText('Could not initialize version comparison.')
		await expect(dialog.locator('.text-comparison')).toHaveCount(0)
	})

	test('C09 navigation cancels a pending snapshot and stale completion cannot publish', async ({ collective, user, page }) => {
		const destination = await collective.createPage({ title: 'c599-e2e-cancel-destination', user, page })
		await destination.setContent({ content: 'Cancellation destination bytes', user, page })
		const { dialog } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-cancel-source')
		let release!: () => void
		const delayed = new Promise<void>((resolve) => {
			release = resolve
		})
		let started = false
		let completed = false
		await page.route(/\/remote\.php\/dav\/versions\//, async (route) => {
			if (route.request().method() !== 'GET' || new URL(route.request().url()).searchParams.has('timestamp')) {
				await route.continue()
				return
			}
			started = true
			await delayed
			await route.fulfill({ body: 'Delayed snapshot' })
			completed = true
		})
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.locator('.version-comparison-dialog__loading')).toBeVisible()
		await expect.poll(() => started).toBe(true)
		await destination.open()
		release()
		await expect.poll(() => completed).toBe(true)
		await expect(page).toHaveURL(new RegExp(destination.getPageUrlPart()))
		await expect(destination.getContent()).toBeVisible()
		await expect(page.locator('.version-comparison-dialog, .text-comparison, [role="alert"]')).toHaveCount(0)
	})

	test('C10 only the latest pair can publish after a delayed superseded response', async ({ collective, user, page }) => {
		const dialog = await openTwoHistoricalSelector(collective, user, page)
		let release!: () => void
		const delayed = new Promise<void>((resolve) => {
			release = resolve
		})
		let started = false
		let completed = false
		await page.route(/\/remote\.php\/dav\/versions\//, async (route) => {
			if (route.request().method() !== 'GET' || new URL(route.request().url()).searchParams.has('timestamp') || started) {
				await route.continue()
				return
			}
			started = true
			const response = await route.fetch()
			await delayed
			await route.fulfill({ response })
			completed = true
		})
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.locator('.version-comparison-dialog__loading')).toBeVisible()
		await expect.poll(() => started).toBe(true)
		// Deliver a selection change while the disabled control has an outstanding request.
		for (const [side, index] of [2, 1].entries()) {
			await dialog.locator('select').nth(side).evaluate((select: HTMLSelectElement, index) => {
				select.selectedIndex = index
				select.dispatchEvent(new Event('change', { bubbles: true }))
			}, index)
		}
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--before')).toContainText('First historical comparison bytes')
		await expect(page.locator('.text-comparison__document--after')).toContainText('Second historical comparison bytes')
		release()
		await expect.poll(() => completed).toBe(true)
		await expect(page.locator('.text-comparison__document--after')).toContainText('Second historical comparison bytes')
		await expect(page.locator('.text-comparison__document--after')).not.toContainText('Current comparison bytes')
		await expect(page.locator('.text-comparison-root')).toHaveCount(1)
	})
})

for (const missingFactory of [false, true]) {
	test(`C08 preparation fails before ${missingFactory ? 'Viewer fallback dispatch' : 'semantic snapshot reads'}`, async ({ collective, user, page, editor }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-preparation-denial', user, page })
		await seedVersionPair(collectivePage, user, page)
		await collectivePage.open()
		const sessionCreated = page.waitForResponse((response) => response.request().method() === 'PUT'
			&& /\/apps\/text\/session\/.*\/create/.test(response.url()))
		await collectivePage.switchMode(true)
		await sessionCreated
		await expect(page.locator('.text-menubar--ready')).toBeVisible()
		let saves = 0
		await page.route(/\/apps\/text\/session\/.*\/save/, async (route) => {
			if (route.request().method() === 'POST') {
				saves++
				await route.fulfill({ status: 500, body: '' })
			} else {
				await route.continue()
			}
		})
		const reads = countSnapshotReads(page)
		editor.setMode(true)
		await editor.getContent().fill('Preparation must fail before snapshot reads')
		await openVersions(page)
		if (missingFactory) {
			await page.evaluate(() => {
				Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', { configurable: true, value: undefined })
			})
		}
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		const dialog = page.getByRole('dialog', { name: 'Compare versions' })
		await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(dialog.getByRole('alert')).toContainText('Could not save current changes before comparison. Please try again.')
		expect(saves).toBeGreaterThan(0)
		expect(reads).toEqual({ historical: [], current: [] })
		await expect(page.locator('.text-comparison, #viewer')).toHaveCount(0)
	})
}

test('X03 missing semantic factory saves current bytes before opening original Viewer panes', async ({ collective, user, page, editor }) => {
	const collectivePage = await collective.createPage({ title: 'c599-e2e-viewer-unsaved', user, page })
	await seedVersionPair(collectivePage, user, page)
	await collectivePage.open()
	const sessionCreated = page.waitForResponse((response) => response.request().method() === 'PUT'
		&& /\/apps\/text\/session\/.*\/create/.test(response.url()))
	await collectivePage.switchMode(true)
	await sessionCreated
	await expect(page.locator('.text-menubar--ready')).toBeVisible()
	const saved = page.waitForResponse((response) => response.request().method() === 'POST'
		&& /\/apps\/text\/session\/.*\/save/.test(response.url()) && response.ok())
	editor.setMode(true)
	const typedBytes = 'No-wait Viewer fallback bytes 7f56c599'
	await editor.getContent().fill(typedBytes)
	await openVersions(page)
	await page.evaluate(() => {
		Object.defineProperty(window.OCA.Text, 'apiVersion', { configurable: true, value: '1.5' })
		Object.defineProperty(window.OCA.Text, 'createMarkdownContentComparison', { configurable: true, value: undefined })
	})
	const reads = countSnapshotReads(page)
	await page.getByRole('button', { name: 'Compare versions…' }).click()
	await page.getByRole('dialog', { name: 'Compare versions' }).getByRole('button', { name: 'Compare', exact: true }).click()
	await saved
	await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
	const panes = page.locator('#viewer .viewer--split > .viewer__file-wrapper:visible')
	await expect(panes).toHaveCount(2)
	await expect(panes.nth(1)).toContainText(typedBytes)
	expect(reads.historical).toHaveLength(1)
	await page.evaluate(() => window.OCA.Viewer.close())
	await expect(page.locator('#viewer')).toHaveCount(0)
})

test('AUD-06 callable semantic factory works despite an unexpected advertised API version', async ({ collective, user, page }) => {
	const { dialog } = await openSeededVersionSelector(collective, user, page, 'c599-e2e-api-capability')
	await page.evaluate(() => {
		Object.defineProperty(window.OCA.Text, 'apiVersion', { configurable: true, value: 'unexpected' })
	})
	await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
	await expect(dialog.locator('.text-comparison')).toBeVisible()
	await expect(dialog.getByRole('tab', { name: 'Markdown source' })).toBeVisible()
	await expect(page.locator('#viewer')).toHaveCount(0)
})
