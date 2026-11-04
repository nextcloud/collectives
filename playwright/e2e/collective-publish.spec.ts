/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Page } from '@playwright/test'

import { expect, mergeTests } from '@playwright/test'
import { test as createCollectivesTest } from '../support/fixtures/create-collectives.ts'
import { test as navigationTest } from '../support/fixtures/navigation.ts'
import { loginAsUser } from '../support/fixtures/random-user.ts'
import { User } from '../support/fixtures/User.ts'
import { pointAtMockStaticSiteGenerator } from '../support/helpers/staticSiteGenerator.ts'
import { getToast } from '../support/helpers/toasts.ts'

type MemberFixture = { page: Page, user: User }

const baseTest = mergeTests(createCollectivesTest, navigationTest)

/**
 * Wrap data in an OCS response body
 *
 * @param data - response data
 */
function ocsResponse(data: unknown) {
	return { ocs: { meta: { status: 'ok', statuscode: 200, message: 'OK' }, data } }
}

/**
 * Seconds since the epoch, as used by the backend for timestamps
 *
 * @param secondsAgo - how long ago
 */
function timestamp(secondsAgo = 0) {
	return Math.floor(Date.now() / 1000) - secondsAgo
}

// Extend fixture to add a logged-in member user for permission tests
const test = baseTest.extend<{ member: MemberFixture }>({
	member: async ({ collective, browser, baseURL }, use) => {
		const account = await collective.addMember()
		const memberPage = await loginAsUser(browser, baseURL, account)
		await use({ page: memberPage, user: new User(account) })
		await memberPage.close()
	},
})

test.beforeAll(async ({ browser, baseURL }) => {
	await pointAtMockStaticSiteGenerator(browser, baseURL)
})

test.describe('Collective publish', () => {
	test('admin can open the publish modal and publish the collective', async ({ collective, navigation, page }) => {
		await collective.openCollective()
		const modal = page.getByRole('dialog')

		await test.step('open and close the publish modal', async () => {
			await page.getByRole('button', { name: 'Collective actions' }).click()

			// Publish button is visible for admin
			const publishButton = page.locator('.action-item__popper:visible')
				.getByRole('button', { name: 'Publish website', exact: true })
			await expect(publishButton).toBeVisible()

			// Publish modal opens on publish button click
			await publishButton.click()
			await expect(modal
				.filter({ has: page.getByRole('heading', { name: `Publish website for Collective ${collective.data.name}` }) }))
				.toBeVisible()

			// Modal can be closed
			await modal.getByRole('button', { name: 'Close' }).click()
			await expect(modal).toHaveCount(0)
		})

		await test.step('close the publish modal with Escape', async () => {
			await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
			await expect(modal).toBeVisible()

			// Escape must also work while a checkbox has the focus
			const checkbox = modal.getByRole('checkbox').first()
			await checkbox.focus()
			await expect(checkbox).toBeFocused()
			await page.keyboard.press('Escape')
			await expect(modal).toHaveCount(0)
		})

		await test.step('reopen the publish modal and publish', async () => {
			// Intercept the API call to verify it is sent and succeeds
			const requestPromise = page.waitForRequest((req) => req.url().includes('/static-sites') && req.method() === 'POST')

			// Modal can be opened again
			await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
			await expect(modal).toBeVisible()
			await expect(modal).toContainText(collective.data.name)

			// Title and web address are prefilled from the collective name
			const titleField = modal.getByRole('textbox', { name: 'Website title' })
			const slugField = modal.getByRole('textbox', { name: 'Web address' })
			const submitButton = modal.getByRole('button', { name: 'Publish website' })
			await expect(titleField).toHaveValue(collective.data.name)
			const suggestedSlug = await slugField.inputValue()
			expect(suggestedSlug).toMatch(/^[a-z0-9]+(-[a-z0-9]+)*$/)

			// Invalid web address prevents publishing
			await slugField.fill('Not a valid slug')
			await expect(submitButton).toBeDisabled()
			await slugField.fill(suggestedSlug)

			// All pages are pre-selected; click publish
			await submitButton.click()

			// The request must reach the backend and provide the website files
			const response = await (await requestPromise).response()
			expect(response).not.toBeNull()
			expect(response!.status()).toBe(200)
			expect((await response!.json()).ocs.data.status).toBe('provided')

			// Modal closes after successful submission
			await expect(modal).toHaveCount(0)
			await expect(getToast(page, 'success', 'Website publishing started')).toBeVisible()
		})
	})

	test('preselects pages created after the page list was loaded if the collective was never published', async ({ collective, navigation, page, user }) => {
		await collective.openCollective()
		// Created via API, so the page list in the browser doesn't contain it yet
		await collective.createPage({ title: 'Page created meanwhile', user, page })

		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await expect(modal.getByRole('checkbox', { name: 'Page created meanwhile' })).toBeChecked()
		await expect(modal.getByRole('button', { name: 'Publish website' })).toBeEnabled()
	})

	test('updates the most recently updated website with its page selection, without deleted and new pages', async ({ collective, navigation, page, user }) => {
		const publishedPage = await collective.createPage({ title: 'Published page', user, page })
		const rootPageId = collective.getRootPage().data.id
		const deletedPageId = 999999
		const olderStaticSite = {
			id: 1,
			title: 'Older website',
			slug: 'older-website',
			selectedPageIds: [rootPageId],
			publishedUrl: 'https://example.org/older-website',
			status: 'published',
			updatedAt: timestamp(7200),
		}
		const staticSite = {
			id: 2,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [rootPageId, publishedPage.data.id, deletedPageId],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
			updatedAt: timestamp(3600),
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([olderStaticSite, staticSite]) })
		})
		let sentBody: { pageIds: number[], title: string, slug?: string } | null = null
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'PUT') {
				return route.fallback()
			}
			// The publish service isn't part of this environment, only the sent settings matter
			sentBody = route.request().postDataJSON()
			await route.fulfill({ json: ocsResponse({ ...staticSite, status: 'provided', updatedAt: timestamp() }) })
		})

		await collective.openCollective()
		await collective.createPage({ title: 'Page created after publication', user, page })

		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		const titleField = modal.getByRole('textbox', { name: 'Website title' })
		const slugField = modal.getByRole('textbox', { name: 'Web address' })

		// Both websites are listed, the most recently updated one is preselected
		await expect(modal.getByRole('button', { name: /Older website/ })).toHaveAttribute('aria-pressed', 'false')
		await expect(modal.getByRole('button', { name: /Published website/ })).toHaveAttribute('aria-pressed', 'true')
		await expect(modal.getByRole('link', { name: staticSite.publishedUrl })).toBeVisible()
		await expect(titleField).toHaveValue(staticSite.title)
		await expect(slugField).toHaveValue(staticSite.slug)
		await expect(slugField).toBeDisabled()
		await expect(modal.getByRole('checkbox', { name: 'Published page' })).toBeChecked()
		await expect(modal.getByRole('checkbox', { name: 'Page created after publication' })).not.toBeChecked()

		// Selecting another website prefills its settings
		await modal.getByRole('button', { name: /Older website/ }).click()
		await expect(titleField).toHaveValue(olderStaticSite.title)
		await expect(modal.getByRole('checkbox', { name: 'Published page' })).not.toBeChecked()
		await modal.getByRole('button', { name: /Published website/ }).click()

		await titleField.fill('New website title')
		await modal.getByRole('button', { name: 'Update website' }).click()
		await expect(modal).toHaveCount(0)
		await expect(getToast(page, 'success', 'Website publishing started')).toBeVisible()
		await expect(getToast(page, 'error', 'Could not publish collective as website')).toHaveCount(0)
		expect(sentBody).not.toBeNull()
		expect(sentBody!.title).toBe('New website title')
		expect(sentBody!.slug).toBeUndefined()
		expect(sentBody!.pageIds).toHaveLength(2)
		expect(sentBody!.pageIds).toEqual(expect.arrayContaining([rootPageId, publishedPage.data.id]))
	})

	test('creates a new website with a web address not used in the collective yet', async ({ collective, navigation, page }) => {
		const staticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [collective.getRootPage().data.id],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
			updatedAt: timestamp(3600),
		}
		let sentBody: { slug: string } | null = null
		await page.route('**/static-sites', async (route) => {
			const request = route.request()
			if (request.method() === 'POST') {
				sentBody = request.postDataJSON()
				return route.fulfill({ json: ocsResponse({ ...staticSite, id: 2, slug: sentBody!.slug, status: 'provided', publishedUrl: null }) })
			}
			if (request.method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([staticSite]) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		const slugField = modal.getByRole('textbox', { name: 'Web address' })
		const submitButton = modal.getByRole('button', { name: 'Publish website' })

		await modal.getByRole('button', { name: 'New website' }).click()
		await expect(modal.getByRole('button', { name: /Published website/ })).toHaveAttribute('aria-pressed', 'false')
		await expect(modal.getByRole('textbox', { name: 'Website title' })).toHaveValue(collective.data.name)
		await expect(slugField).toBeEnabled()

		// The web address of the existing website can't be used for a new one
		await slugField.fill(staticSite.slug)
		await expect(modal).toContainText('This web address is already used by another website of this collective')
		await expect(submitButton).toBeDisabled()

		await slugField.fill('second-website')
		await submitButton.click()
		await expect(modal).toHaveCount(0)
		expect(sentBody!.slug).toBe('second-website')
	})

	test('prevents updating a website while it is being published', async ({ collective, navigation, page }) => {
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({
				json: ocsResponse([{
					id: 1,
					title: 'Website in progress',
					slug: 'website-in-progress',
					selectedPageIds: [collective.getRootPage().data.id],
					publishedUrl: null,
					status: 'provided',
					updatedAt: timestamp(60),
				}]),
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await expect(modal.getByRole('button', { name: /Website in progress/ })).toContainText('Publishing')
		await expect(modal.getByRole('button', { name: 'Update website' })).toBeDisabled()

		// Another website can be created meanwhile
		await modal.getByRole('button', { name: 'New website' }).click()
		await modal.getByRole('textbox', { name: 'Web address' }).fill('another-website')
		await expect(modal.getByRole('button', { name: 'Publish website' })).toBeEnabled()
	})

	test('keeps the modal open if the website files could not be provided', async ({ collective, navigation, page }) => {
		// A failed archive build can't be provoked reliably, so the response is replaced
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'POST') {
				return route.fallback()
			}
			await route.fulfill({ status: 500, json: { ocs: { meta: { status: 'failure', statuscode: 500, message: '' }, data: [] } } })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		const titleField = modal.getByRole('textbox', { name: 'Website title' })
		await titleField.fill('My website')
		await modal.getByRole('button', { name: 'Publish website' }).click()

		await expect(getToast(page, 'error', 'Could not publish collective as website')).toBeVisible()
		// Entered values remain for another attempt
		await expect(modal).toBeVisible()
		await expect(titleField).toHaveValue('My website')
	})

	test('shows the published URL in the modal if the collective is already published', async ({ collective, navigation, page }) => {
		const publishedUrl = 'https://example.org/published-site'
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([{ id: 1, title: 'Published website', slug: 'published-website', selectedPageIds: [], publishedUrl, status: 'published', updatedAt: timestamp(3600) }]) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await expect(modal.getByRole('link', { name: publishedUrl })).toBeVisible()
	})

	test('shows a toast once the published URL arrives, without the modal being open', async ({ collective, navigation, page }) => {
		// Polling for the published URL takes a few seconds
		test.slow()

		const publishedUrl = 'https://example.org/freshly-published'
		let publishedStaticSite: object | null = null
		await page.route('**/static-sites', async (route) => {
			const request = route.request()
			if (request.method() === 'POST') {
				// The publish service isn't part of this environment, so its result is mocked
				const response = await route.fetch()
				const staticSite = (await response.json()).ocs.data
				publishedStaticSite = { ...staticSite, status: 'published', publishedUrl }
				return route.fulfill({ response })
			}
			if (request.method() !== 'GET') {
				return route.fallback()
			}
			const data = publishedStaticSite ? [publishedStaticSite] : []
			await route.fulfill({ json: { ocs: { meta: { status: 'ok', statuscode: 200, message: 'OK' }, data } } })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('textbox', { name: 'Website title' }).fill('My website')
		await modal.getByRole('button', { name: 'Publish website' }).click()
		await expect(modal).toHaveCount(0)

		// The published URL arrives in the background, no modal involved
		await expect(getToast(page, 'success', publishedUrl))
			.toContainText(`Website "My website" published at ${publishedUrl}`, { timeout: 45_000 })
	})

	test('regular members cannot see publish button', async ({ collective, member }) => {
		await member.page.goto(`/index.php/apps/collectives/${collective.getCollectiveUrlPart()}`)

		// Open the current collective's actions menu
		await member.page.getByRole('button', { name: 'Collective actions' }).click()

		// Actions menu is visible for regular member, but without the publish button
		const actionsMenu = member.page.locator('.action-item__popper:visible')
		await expect(actionsMenu).toBeVisible()

		const publishButton = actionsMenu.getByRole('button', { name: 'Publish website', exact: true })
		await expect(publishButton).toHaveCount(0)
	})
})
