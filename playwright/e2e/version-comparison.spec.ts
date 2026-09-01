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
	const opener = page.getByRole('button', { name: 'Compare versions…' })
	await opener.click()
	const dialog = page.getByRole('dialog', { name: 'Compare versions' })
	await expect(dialog).toBeVisible()
	await expect.poll(() => dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true)
	return { dialog, opener }
}

async function openSeededComparison(collective: Collective, user: User, page: Page, title: string) {
	const { dialog, opener } = await openSeededVersionSelector(collective, user, page, title)
	await dialog.getByRole('button', { name: 'Compare', exact: true }).click()
	await expect(page.locator('.text-comparison')).toBeVisible()
	return { dialog, opener }
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
	test('AUD-06 scheduled browser uses the compatible Text comparison API', async ({ user, page, collective }) => {
		const { dialog } = await openSeededComparison(collective, user, page, 'c599-e2e-text-api-page')
		await expect(page.getByRole('tab', { name: 'Changes' })).toBeVisible()
		await expect(page.getByRole('tab', { name: 'Full documents' })).toBeVisible()
		const changesTab = page.locator('.text-comparison .view-tabs').getByRole('tab', { name: 'Changes' })
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
		const sessionCreated = page.waitForResponse((response) => response.request().method() === 'PUT'
			&& /\/apps\/text\/session\/.*\/create/.test(response.url()))
		await collectivePage.switchMode(true)
		await sessionCreated
		await expect(page.locator('.text-menubar--ready')).toBeVisible()
		editor.setMode(true)
		const typedBytes = 'No-wait Playwright bytes 7f56c599'
		await editor.getContent().fill(typedBytes)

		await openVersions(page)
		await page.getByRole('button', { name: 'Compare versions…' }).click()
		await page.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await page.getByRole('tab', { name: 'Full documents' }).click()
		await expect(page.locator('.text-comparison__document--after')).toContainText(typedBytes)
		await expect(collectivePage.getContent(true)).toBeAttached()
	})

	test('R01 encodes the exact ordered snapshot pair in the canonical route', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-canonical-route-page')
		const comparison = new URL(page.url())
		expect(comparison.searchParams.get('compareFrom')).toMatch(/^version:[^/\\]+$/)
		expect(comparison.searchParams.get('compareTo')).toMatch(/^current:[^/\\]+$/)
		const selectors = page.locator('.version-comparison-dialog select')
		await expect(selectors.nth(0)).toHaveValue(comparison.searchParams.get('compareFrom')!)
		await expect(selectors.nth(1)).toHaveValue('current')
	})

	test('R02 reload restores the exact comparison pair and view', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-reload-route-page')
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
	})

	test('R03 Back closes the managed comparison and restores the prior route', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-back-route-page')

		await page.goBack()

		await expect(page.locator('.version-comparison-dialog')).toHaveCount(0)
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

	test('R05 copied link opens the exact pair in a fresh authenticated context', async ({ user, page, collective }) => {
		await openSeededComparison(collective, user, page, 'c599-e2e-copied-route-page')
		const comparisonUrl = page.url()

		await installClipboardCapture(page)
		await page.getByRole('button', { name: 'Copy comparison link' }).click()
		const copiedUrl = await page.evaluate(() => sessionStorage.getItem('c599-copied-link'))
		expect(copiedUrl).toBe(comparisonUrl)

		const copiedContext = await freshAuthenticatedContext(page)
		const copiedPage = await copiedContext.newPage()
		await copiedPage.goto(copiedUrl!)
		await expect(copiedPage.locator('.text-comparison')).toBeVisible()
		await copiedPage.getByRole('tab', { name: 'Full documents' }).click()
		await expect(copiedPage.locator('.text-comparison__document--before')).toContainText('Historical comparison bytes')
		await expect(copiedPage.locator('.text-comparison__document--after')).toContainText('Current comparison bytes')
		const copiedPair = new URL(copiedPage.url())
		expect(copiedPair.searchParams.get('compareFrom')).toBe(new URL(comparisonUrl).searchParams.get('compareFrom'))
		expect(copiedPair.searchParams.get('compareTo')).toBe(new URL(comparisonUrl).searchParams.get('compareTo'))
		await copiedContext.close()
	})

	test('F11 completes comparison with no unexplained browser or network failures', async ({ user, page, collective }) => {
		const assertNoFailures = auditComparisonFailures(page)
		await openSeededComparison(collective, user, page, 'c599-e2e-clean-failures-page')
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
		await openVersions(readerPage)
		await readerPage.getByRole('button', { name: 'Compare versions…' }).click()
		await readerPage.getByRole('dialog').getByRole('button', { name: 'Compare', exact: true }).click()
		await expect(readerPage.locator('.text-comparison')).toBeVisible()
		expect(snapshotStatuses.length).toBeGreaterThan(0)
		expect(snapshotStatuses.every((status) => status >= 200 && status < 300)).toBe(true)
		await readerPage.close()
	})

	test('C16 rejects public comparison parameters without snapshot requests', async ({ user, page, collective }) => {
		const collectivePage = await collective.createPage({ title: 'c599-e2e-public-comparison-page', user, page })
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
		const sessionCreated = page.waitForResponse((response) => response.request().method() === 'PUT'
			&& /\/apps\/text\/session\/.*\/create/.test(response.url()))
		await collectivePage.switchMode(true)
		await sessionCreated
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
