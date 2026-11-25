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

/**
 * Row of the page tree in the publish modal, the home row included.
 *
 * @param page the Playwright page
 * @param title title of the page
 */
function publishTreeRow(page: Page, title: string) {
	return page.getByRole('dialog').locator('.publish-tree-row').filter({ hasText: title })
}

/**
 * Toggle the selection of a page tree row. Its checkbox itself is not clickable.
 *
 * @param page the Playwright page
 * @param title title of the page
 */
async function clickPublishTreeCheckbox(page: Page, title: string) {
	await publishTreeRow(page, title).locator('.checkbox-radio-switch__content').click()
}

/**
 * Card of a website in the website list of the publish modal
 *
 * @param page the Playwright page
 * @param title title of the website
 */
function websiteCard(page: Page, title: string) {
	return page.getByRole('dialog').getByRole('listitem').filter({ hasText: title })
}

/**
 * Open the actions menu of a website card and get one of its actions
 *
 * @param page the Playwright page
 * @param title title of the website
 * @param action name of the action
 */
async function websiteAction(page: Page, title: string, action: string) {
	await page.getByRole('button', { name: `Actions for website ${title}` }).click()
	return page.getByRole('menuitem', { name: action })
}

test.describe('Collective publish', () => {
	test('admin can open the publish modal and publish the collective', async ({ collective, navigation, page, user }) => {
		// A page besides the home page, whose checkbox can get the focus
		await collective.createPage({ title: 'Subpage', user, page })
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
			await expect(modal.filter({ has: page.getByRole('heading', { name: 'Websites' }) })).toBeVisible()
			// Without websites, the modal explains how publishing works
			await expect(modal.getByRole('heading', { name: 'No website online yet' })).toBeVisible()
			await expect(modal.getByRole('listitem')).toHaveText([/Choose pages/, /Set address and look/, /Update anytime/])

			// Modal can be closed
			await modal.getByRole('button', { name: 'Close' }).click()
			await expect(modal).toHaveCount(0)
		})

		await test.step('close the publish modal with Escape', async () => {
			await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
			await modal.getByRole('button', { name: 'Create website' }).click()

			// Escape must also work while a checkbox has the focus
			// The home page checkbox is disabled and can't get the focus
			const checkbox = modal.getByRole('checkbox', { disabled: false }).first()
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
			await modal.getByRole('button', { name: 'Create website' }).click()
			await expect(modal.getByRole('heading', { name: 'New website' })).toBeVisible()
			await modal.getByRole('button', { name: 'Continue' }).click()

			// The title is prefilled from the collective name, the web address has to be entered
			const titleField = modal.getByRole('textbox', { name: 'Website title' })
			const slugField = modal.getByRole('textbox', { name: 'Website address' })
			const submitButton = modal.getByRole('button', { name: 'Publish website' })
			await expect(titleField).toHaveValue(collective.data.name)
			await expect(slugField).toHaveValue('')
			await expect(submitButton).toBeDisabled()
			await expect(titleField).toHaveAttribute('required')
			await expect(slugField).toHaveAttribute('required')

			// Invalid web address prevents publishing
			await slugField.fill('Not a valid slug')
			await expect(submitButton).toBeDisabled()
			// Web addresses are unique across all collectives
			await slugField.fill(`website-${collective.data.id}`)

			// Wiki is preselected, switching to Menu bar must reach the backend as "topnav".
			// The native radio itself is visually hidden, so its label card is clicked instead.
			await expect(modal.getByRole('radio', { name: 'Wiki' })).toBeChecked()
			await modal.locator('.theme-option').filter({ hasText: 'Menu bar' }).click()
			await expect(modal.getByRole('radio', { name: 'Menu bar' })).toBeChecked()

			// All pages are pre-selected; click publish
			await submitButton.click()

			// The request must reach the backend and provide the website files
			const response = await (await requestPromise).response()
			expect(response).not.toBeNull()
			expect(response!.status()).toBe(200)
			const responseData = (await response!.json()).ocs.data
			expect(responseData.status).toBe('provided')
			expect(responseData.theme).toBe('topnav')

			// The website list shows the website being published
			await expect(getToast(page, 'success', 'Website publishing started')).toBeVisible()
			await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
			await expect(websiteCard(page, collective.data.name)).toContainText('Publishing')
			await expect(modal.getByRole('button', { name: 'New website' })).toBeDisabled()
		})
	})

	test('preselects pages created after the page list was loaded if the collective was never published', async ({ collective, navigation, page, user }) => {
		await collective.openCollective()
		// Created via API, so the page list in the browser doesn't contain it yet
		await collective.createPage({ title: 'Page created meanwhile', user, page })

		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('button', { name: 'Create website' }).click()
		await expect(modal.getByRole('checkbox', { name: 'Page created meanwhile' })).toBeChecked()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await modal.getByRole('textbox', { name: 'Website address' }).fill('my-website')
		await expect(modal.getByRole('button', { name: 'Publish website' })).toBeEnabled()
	})

	test('keeps entered values between the steps and asks before discarding them', async ({ collective, navigation, page }) => {
		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		// The confirmation is a dialog of its own
		const modal = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: /Websites|New website/ }) })
		const confirmation = page.getByRole('dialog', { name: 'Discard changes?' })
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await expect(modal.locator('[aria-current="step"]')).toHaveText(/Settings/)

		const slugField = modal.getByRole('textbox', { name: 'Website address' })
		await slugField.fill('my-website')
		await modal.getByRole('button', { name: 'Back', exact: true }).click()
		await expect(modal.locator('[aria-current="step"]')).toHaveText(/Page selection/)
		await modal.getByRole('button', { name: 'Continue' }).click()
		await expect(slugField).toHaveValue('my-website')

		// Leaving the form with changes needs a confirmation
		await modal.getByRole('button', { name: 'Back to websites' }).click()
		await confirmation.getByRole('button', { name: 'Keep editing' }).click()
		await expect(confirmation).toHaveCount(0)
		await expect(slugField).toHaveValue('my-website')

		// Closing the modal can be cancelled as well
		await modal.getByRole('button', { name: 'Close' }).click()
		await confirmation.getByRole('button', { name: 'Keep editing' }).click()
		await expect(slugField).toBeVisible()
		await expect(slugField).toHaveValue('my-website')

		await modal.getByRole('button', { name: 'Back to websites' }).click()
		await confirmation.getByRole('button', { name: 'Discard changes' }).click()
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()

		// Without changes, the form is left right away
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Back to websites' }).click()
		await expect(confirmation).toHaveCount(0)
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
	})

	test('selects ancestor pages with a subpage and deselects a subtree with its parent', async ({ collective, navigation, page, user }) => {
		const parentPage = await collective.createPage({ title: 'Parent page', user, page })
		const childPage = await collective.createPage({ title: 'Child page', parentId: parentPage.data.id, user, page })
		await collective.createPage({ title: 'Grandchild page', parentId: childPage.data.id, user, page })

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('button', { name: 'Create website' }).click()
		const homeCheckbox = modal.getByRole('checkbox', { name: 'Home' })
		const parentCheckbox = modal.getByRole('checkbox', { name: 'Parent page' })
		const childCheckbox = modal.getByRole('checkbox', { name: 'Child page' })

		// The home page is always part of the website
		await expect(homeCheckbox).toBeChecked()
		await expect(homeCheckbox).toBeDisabled()
		await expect(publishTreeRow(page, 'Home')).toContainText('The home page is always part of the website.')

		// Subpages are collapsed by default, the counter includes subpages at any depth
		await expect(publishTreeRow(page, 'Parent page')).toContainText('2/2 subpages')
		await publishTreeRow(page, 'Parent page').getByRole('button', { name: 'Expand subpages' }).click()
		await expect(childCheckbox).toBeChecked()
		await expect(publishTreeRow(page, 'Child page')).toContainText('1/1 subpage')

		// Deselecting a page also deselects its subpages, but leaves its ancestors alone
		await clickPublishTreeCheckbox(page, 'Child page')
		await expect(childCheckbox).not.toBeChecked()
		await expect(parentCheckbox).toBeChecked()
		await expect(publishTreeRow(page, 'Parent page')).toContainText('0/2 subpages')
		await expect(modal).toContainText('2 of 4 pages selected')

		await clickPublishTreeCheckbox(page, 'Parent page')
		await expect(parentCheckbox).not.toBeChecked()
		await expect(homeCheckbox).toBeChecked()
		await expect(modal).toContainText('1 of 4 pages selected')

		// Selecting a subpage selects its own subpages and pulls its parent page back in
		await clickPublishTreeCheckbox(page, 'Child page')
		await expect(childCheckbox).toBeChecked()
		await expect(parentCheckbox).toBeChecked()
		await expect(publishTreeRow(page, 'Parent page')).toContainText('2/2 subpages')
		await expect(modal).toContainText('4 of 4 pages selected')
	})

	test('filters the pages and selects all or none of them', async ({ collective, navigation, page, user }) => {
		const meetingsPage = await collective.createPage({ title: 'Meetings', user, page })
		await collective.createPage({ title: 'Weekly notes', parentId: meetingsPage.data.id, user, page })
		await collective.createPage({ title: 'Retro', parentId: meetingsPage.data.id, user, page })
		await collective.createPage({ title: 'Handbook', user, page })

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('button', { name: 'Create website' }).click()
		const filterField = modal.getByRole('textbox', { name: 'Filter pages' })

		// Matching pages are shown expanded with their ancestors, the matching text is bold
		await filterField.fill('notes')
		await expect(modal.getByRole('checkbox', { name: 'Weekly notes' })).toBeVisible()
		await expect(modal.getByRole('checkbox', { name: 'Meetings' })).toBeVisible()
		await expect(modal.getByRole('checkbox', { name: 'Retro' })).toHaveCount(0)
		await expect(modal.getByRole('checkbox', { name: 'Handbook' })).toHaveCount(0)
		await expect(publishTreeRow(page, 'Weekly notes').locator('strong')).toHaveText('notes')
		await expect(publishTreeRow(page, 'Meetings').locator('strong')).toHaveCount(0)
		await expect(modal.getByRole('button', { name: 'Expand subpages' })).toHaveCount(0)

		// Select none and all affect the pages hidden by the filter as well, but keep the home page
		await modal.getByRole('button', { name: 'Select none' }).click()
		await expect(modal.getByRole('checkbox', { name: 'Weekly notes' })).not.toBeChecked()
		await expect(modal.getByRole('checkbox', { name: 'Home' })).toBeChecked()
		await expect(modal).toContainText('1 of 5 pages selected')

		await filterField.fill('missing')
		await expect(modal).toContainText('No pages match the filter.')

		await modal.getByRole('button', { name: 'Clear filter' }).click()
		await expect(modal.getByRole('checkbox', { name: 'Handbook' })).not.toBeChecked()
		// Subpages are collapsed again without a filter
		await expect(modal.getByRole('checkbox', { name: 'Retro' })).toHaveCount(0)

		await modal.getByRole('button', { name: 'Select all' }).click()
		await expect(modal.getByRole('checkbox', { name: 'Handbook' })).toBeChecked()
		await expect(modal).toContainText('5 of 5 pages selected')
		await publishTreeRow(page, 'Meetings').getByRole('button', { name: 'Expand subpages' }).click()
		await expect(modal.getByRole('checkbox', { name: 'Retro' })).toBeChecked()
	})

	test('preselects the current ancestors of a page that was moved since the last publication', async ({ collective, navigation, page, user }) => {
		const newParentPage = await collective.createPage({ title: 'New parent page', user, page })
		const movedPage = await collective.createPage({ title: 'Moved page', parentId: newParentPage.data.id, user, page })
		const staticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			// The page was published as a top level page, so its new parent page is missing
			selectedPageIds: [collective.getRootPage().data.id, movedPage.data.id],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: { ocs: { meta: { status: 'ok', statuscode: 200, message: 'OK' }, data: [staticSite] } } })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await (await websiteAction(page, 'Published website', 'Edit')).click()
		await publishTreeRow(page, 'New parent page').getByRole('button', { name: 'Expand subpages' }).click()

		await expect(modal.getByRole('checkbox', { name: 'Moved page' })).toBeChecked()
		await expect(modal.getByRole('checkbox', { name: 'New parent page' })).toBeChecked()
		await expect(modal.getByRole('checkbox', { name: 'Home' })).toBeChecked()
	})

	test('updates a website with its page selection, without deleted and new pages', async ({ collective, navigation, page, user }) => {
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
			await route.fulfill({ json: ocsResponse([staticSite, olderStaticSite]) })
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
		const slugField = modal.getByRole('textbox', { name: 'Website address' })

		// Websites are listed by title
		await expect(modal.getByRole('listitem')).toHaveText([/Older website/, /Published website/])
		await expect(modal).toContainText('2 websites · 2 live')
		await expect(modal.getByRole('link', { name: staticSite.publishedUrl })).toBeVisible()

		// Editing a website prefills its settings
		await (await websiteAction(page, 'Older website', 'Edit')).click()
		await expect(modal.getByRole('heading', { name: 'Update website' })).toBeVisible()
		await expect(modal.getByRole('checkbox', { name: 'Published page' })).not.toBeChecked()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await expect(titleField).toHaveValue(olderStaticSite.title)
		await modal.getByRole('button', { name: 'Back to websites' }).click()

		await (await websiteAction(page, 'Published website', 'Edit')).click()
		await expect(modal.getByRole('checkbox', { name: 'Published page' })).toBeChecked()
		await expect(modal.getByRole('checkbox', { name: 'Page created after publication' })).not.toBeChecked()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await expect(titleField).toHaveValue(staticSite.title)
		await expect(slugField).toHaveValue(staticSite.slug)
		await expect(slugField).toBeDisabled()

		await titleField.fill('New website title')
		await modal.getByRole('button', { name: 'Update website' }).click()
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
		await expect(getToast(page, 'success', 'Website publishing started')).toBeVisible()
		await expect(getToast(page, 'error', 'Could not publish collective as website')).toHaveCount(0)
		expect(sentBody).not.toBeNull()
		expect(sentBody!.title).toBe('New website title')
		expect(sentBody!.slug).toBe(staticSite.slug)
		expect(sentBody!.pageIds).toHaveLength(2)
		expect(sentBody!.pageIds).toEqual(expect.arrayContaining([rootPageId, publishedPage.data.id]))
	})

	test('updates a website whose pages changed since its publication after reviewing the changes', async ({ collective, navigation, page }) => {
		const rootPageId = collective.getRootPage().data.id
		const deletedPageId = 999999
		const staticSite = {
			id: 1,
			title: 'Outdated website',
			slug: 'outdated-website',
			theme: 'topnav',
			selectedPageIds: [rootPageId, deletedPageId],
			publishedSelectedPageIds: [rootPageId, deletedPageId],
			publishedUrl: 'https://example.org/outdated-website',
			publishedAt: timestamp(3600),
			publishedBuildStartedAt: timestamp(3600),
			status: 'published',
			isInProgress: false,
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([staticSite]) })
		})
		let sentBody: { pageIds: number[], title: string, slug: string } | null = null
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'PUT') {
				return route.fallback()
			}
			sentBody = route.request().postDataJSON()
			await route.fulfill({ json: ocsResponse({ ...staticSite, status: 'provided', isInProgress: true, secondsUntilTimeout: 900 }) })
		})

		// The home page got created after the publication, so it changed since
		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const card = websiteCard(page, staticSite.title)
		const review = page.getByRole('dialog', { name: 'Update website "Outdated website"?' })

		await expect(card).toContainText('Live · changes not published')
		await card.getByRole('button', { name: 'Update website Outdated website' }).click()
		await expect(review).toContainText(`The whole website at ${staticSite.publishedUrl} is published again.`)
		// Changed pages link to the page, deleted ones are only counted
		await expect(review.getByRole('link', { name: 'Home' })).toHaveAttribute('target', '_blank')
		await expect(review).toContainText('1 published page was deleted and gets removed from the website.')

		await review.getByRole('button', { name: 'Publish' }).click()
		await expect(review).toHaveCount(0)
		await expect(getToast(page, 'success', 'Website publishing started')).toBeVisible()
		// The website is published again with its settings, without the deleted page
		expect(sentBody).toEqual({ pageIds: [rootPageId], title: staticSite.title, slug: staticSite.slug, theme: staticSite.theme })
	})

	test('retries publishing a website whose first publication failed after reviewing its pages', async ({ collective, navigation, page, user }) => {
		const childPage = await collective.createPage({ title: 'Child page', user, page })
		const staticSite = {
			id: 1,
			title: 'Failed website',
			slug: 'failed-website',
			selectedPageIds: [collective.getRootPage().data.id, childPage.data.id],
			publishedUrl: null,
			status: 'failed',
			isInProgress: false,
			lastError: 'generator_unavailable',
			updatedAt: timestamp(60),
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([staticSite]) })
		})
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'PUT') {
				return route.fallback()
			}
			await route.fulfill({
				status: 503,
				json: { ocs: { meta: { status: 'failure', statuscode: 503, message: 'The publish service is currently not available. Please try again later.' }, data: [] } },
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const review = page.getByRole('dialog', { name: 'Retry publishing website "Failed website"?' })

		await websiteCard(page, staticSite.title).getByRole('button', { name: 'Retry publishing website Failed website' }).click()
		await expect(review).toContainText('The last attempt failed a minute ago: The publish service is currently not available. Please try again later.')
		await expect(review).toContainText('These 2 pages go online at https://example.com/sites/failed-website:')
		await expect(review.getByRole('listitem')).toHaveText(['Home', 'Child page'])

		// The dialog stays open to try again
		await review.getByRole('button', { name: 'Publish' }).click()
		await expect(getToast(page, 'error', 'Could not publish collective as website')).toBeVisible()
		await expect(review).toBeVisible()
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
		const slugField = modal.getByRole('textbox', { name: 'Website address' })
		const submitButton = modal.getByRole('button', { name: 'Publish website' })

		await modal.getByRole('button', { name: 'New website' }).click()
		await expect(modal.getByRole('heading', { name: 'New website' })).toBeVisible()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await expect(modal.getByRole('textbox', { name: 'Website title' })).toHaveValue(collective.data.name)
		await expect(slugField).toBeEnabled()
		await expect(slugField).toHaveValue('')

		// The web address of the existing website can't be used for a new one
		await slugField.fill(staticSite.slug)
		await expect(modal).toContainText('This web address is already taken by another website')
		await expect(submitButton).toBeDisabled()

		await slugField.fill('second-website')
		await submitButton.click()
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
		expect(sentBody!.slug).toBe('second-website')
	})

	test('changes the web address of a website that was never published', async ({ collective, navigation, page }) => {
		const rootPageId = collective.getRootPage().data.id
		const publishedStaticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [rootPageId],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
			updatedAt: timestamp(7200),
		}
		const failedStaticSite = {
			id: 2,
			title: 'Failed website',
			slug: 'failed-website',
			selectedPageIds: [rootPageId],
			publishedUrl: null,
			status: 'failed',
			isInProgress: false,
			lastError: 'generator_unavailable',
			updatedAt: timestamp(3600),
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([failedStaticSite, publishedStaticSite]) })
		})
		const sentSlugs: string[] = []
		await page.route(`**/static-sites/${failedStaticSite.id}`, async (route) => {
			if (route.request().method() !== 'PUT') {
				return route.fallback()
			}
			const { slug } = route.request().postDataJSON()
			sentSlugs.push(slug)
			if (slug === 'taken-website') {
				// Websites of other collectives aren't known to the browser, the backend reports the conflict
				return route.fulfill({
					status: 409,
					json: { ocs: { meta: { status: 'failure', statuscode: 409, message: '' }, data: { message: 'This web address is already taken by another website' } } },
				})
			}
			await route.fulfill({ json: ocsResponse({ ...failedStaticSite, slug, status: 'provided', updatedAt: timestamp() }) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		const slugField = modal.getByRole('textbox', { name: 'Website address' })
		const submitButton = modal.getByRole('button', { name: 'Update website' })

		await (await websiteAction(page, 'Failed website', 'Edit')).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		// The own web address isn't taken
		await expect(slugField).toBeEnabled()
		await expect(slugField).toHaveValue(failedStaticSite.slug)
		await expect(submitButton).toBeEnabled()

		await slugField.fill(publishedStaticSite.slug)
		await expect(modal).toContainText('This web address is already taken by another website')
		await expect(submitButton).toBeDisabled()

		await slugField.fill('taken-website')
		await submitButton.click()
		await expect(modal).toContainText('This web address is already taken by another website')
		await expect(submitButton).toBeDisabled()

		await slugField.fill('new-website')
		await submitButton.click()
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
		expect(sentSlugs).toEqual(['taken-website', 'new-website'])
	})

	test('shows a web address taken by a website of another collective at the field', async ({ collective, navigation, page }) => {
		// Websites of other collectives aren't known to the browser, the backend reports the conflict
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'POST') {
				return route.fallback()
			}
			await route.fulfill({
				status: 409,
				json: { ocs: { meta: { status: 'failure', statuscode: 409, message: '' }, data: { message: 'This web address is already taken by another website' } } },
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		const slugField = modal.getByRole('textbox', { name: 'Website address' })
		const submitButton = modal.getByRole('button', { name: 'Publish website' })

		await slugField.fill('taken-website')
		await submitButton.click()

		await expect(modal).toContainText('This web address is already taken by another website')
		await expect(submitButton).toBeDisabled()
		// No toast, client errors are shown as warning
		await expect(page.getByText('Could not publish collective as website')).toHaveCount(0)

		await slugField.fill('another-website')
		await expect(submitButton).toBeEnabled()
	})

	test('prevents publishing while a website of the collective is being published', async ({ collective, navigation, page }) => {
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
					isInProgress: true,
					updatedAt: timestamp(60),
				}]),
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await expect(websiteCard(page, 'Website in progress')).toContainText('Publishing')
		await expect(modal.getByRole('button', { name: 'New website' })).toBeDisabled()
		await expect(await websiteAction(page, 'Website in progress', 'Edit')).toBeDisabled()
		await expect(page.getByRole('menuitem', { name: 'Delete website' })).toBeDisabled()
	})

	test('returns to the website list if another publication of the collective started meanwhile', async ({ collective, navigation, page }) => {
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'POST') {
				return route.fallback()
			}
			await route.fulfill({
				status: 423,
				json: { ocs: { meta: { status: 'failure', statuscode: 423, message: 'A website of this collective is being published or deleted. Please wait until it is finished.' }, data: [] } },
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await modal.getByRole('textbox', { name: 'Website address' }).fill('my-website')
		await modal.getByRole('button', { name: 'Publish website' }).click()

		// Client errors are shown as warning toast with role status, but the modal is still open and shows the website list with the in-progress website
		await expect(page.getByRole('status').filter({ hasText: 'A website of this collective is being published or deleted. Please wait until it is finished.' })).toBeVisible()
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
	})

	test('shows a new website as failed if the publish service is not available', async ({ collective, navigation, page }) => {
		let failedStaticSite: object | null = null
		await page.route('**/static-sites', async (route) => {
			const request = route.request()
			if (request.method() === 'POST') {
				// The backend keeps the website as failed, so publishing can be retried later
				failedStaticSite = {
					id: 1,
					title: collective.data.name,
					slug: request.postDataJSON().slug,
					selectedPageIds: [collective.getRootPage().data.id],
					publishedUrl: null,
					publishedAt: null,
					status: 'failed',
					isInProgress: false,
					lastError: 'generator_unavailable',
					updatedAt: timestamp(),
				}
				return route.fulfill({
					status: 503,
					json: { ocs: { meta: { status: 'failure', statuscode: 503, message: 'The publish service is currently not available. Please try again later.' }, data: [] } },
				})
			}
			await route.fulfill({ json: ocsResponse(failedStaticSite ? [failedStaticSite] : []) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await modal.getByRole('textbox', { name: 'Website address' }).fill('my-website')
		await modal.getByRole('button', { name: 'Publish website' }).click()

		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
		const card = websiteCard(page, collective.data.name)
		await expect(card).toContainText('Failed')
		await expect(card).toContainText('The website is not online.')
		await expect(await websiteAction(page, collective.data.name, 'Edit')).toBeEnabled()
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
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		const titleField = modal.getByRole('textbox', { name: 'Website title' })
		await titleField.fill('My website')
		await modal.getByRole('textbox', { name: 'Website address' }).fill('my-website')
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
			await route.fulfill({ json: { ocs: { meta: { status: 'ok', statuscode: 200, message: 'OK' }, data: [{ id: 1, title: 'Published website', slug: 'published-website', selectedPageIds: [], publishedUrl }] } } })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog')
		await expect(modal.getByRole('link', { name: publishedUrl })).toBeVisible()
	})

	test('shows why publishing a website failed', async ({ collective, navigation, page }) => {
		const rootPageId = collective.getRootPage().data.id
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({
				json: ocsResponse([
					{
						id: 1,
						title: 'Previously published website',
						slug: 'previously-published-website',
						selectedPageIds: [rootPageId],
						publishedUrl: 'https://example.org/previously-published-website',
						publishedAt: timestamp(7200),
						status: 'failed',
						lastError: 'generator_unavailable',
						updatedAt: timestamp(3600),
					},
					{
						id: 2,
						title: 'Never published website',
						slug: 'never-published-website',
						selectedPageIds: [rootPageId],
						publishedUrl: null,
						publishedAt: null,
						status: 'failed',
						lastError: 'generator_failed',
						updatedAt: timestamp(7200),
					},
					{
						id: 3,
						title: 'Aborted website',
						slug: 'aborted-website',
						selectedPageIds: [rootPageId],
						publishedUrl: null,
						publishedAt: null,
						status: 'provided',
						isInProgress: false,
						lastError: null,
						updatedAt: timestamp(7200),
					},
				]),
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const previouslyPublished = websiteCard(page, 'Previously published website')
		const neverPublished = websiteCard(page, 'Never published website')
		const abortedPublication = websiteCard(page, 'Aborted website')

		await expect(previouslyPublished).toContainText('Failed')
		await expect(previouslyPublished).toContainText('The publish service is currently not available. Please try again later.')
		await expect(previouslyPublished).toContainText('The previous version is still online.')
		// Relative time in the text, the exact time in the tooltip
		await expect(previouslyPublished.getByText('Published 2 hours ago')).toHaveAttribute('title', /\d/)

		await expect(neverPublished).toContainText('The publish service could not build the website. Please contact your administrator.')
		await expect(neverPublished).toContainText('The website is not online.')
		await expect(neverPublished).toContainText('Not published yet')

		// A publication without result from the publish service counts as failed
		await expect(abortedPublication).toContainText('Failed')
		await expect(abortedPublication).toContainText('The publish service didn\'t respond in time.')

		// A failed publication can be retried from the card
		await expect(neverPublished.getByRole('button', { name: 'Retry publishing website Never published website' })).toBeEnabled()
		await previouslyPublished.getByRole('button', { name: 'Retry publishing website Previously published website' }).click()
		const review = page.getByRole('dialog', { name: 'Retry publishing website "Previously published website"?' })
		await expect(review).toContainText('This page goes online at https://example.org/previously-published-website:')
		await review.getByRole('button', { name: 'Cancel' }).click()
		await expect(review).toHaveCount(0)
		// The web address is shown before the website is online
		await expect(neverPublished).toContainText('https://example.com/sites/never-published-website')
		await expect(neverPublished.getByRole('link')).toHaveCount(0)
		await expect(await websiteAction(page, 'Never published website', 'Edit')).toBeEnabled()
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
		await modal.getByRole('button', { name: 'Create website' }).click()
		await modal.getByRole('button', { name: 'Continue' }).click()
		await modal.getByRole('textbox', { name: 'Website title' }).fill('My website')
		// The request reaches the backend, where web addresses are unique across all collectives
		await modal.getByRole('textbox', { name: 'Website address' }).fill(`website-${collective.data.id}`)
		await modal.getByRole('button', { name: 'Publish website' }).click()
		await expect(modal.getByRole('heading', { name: 'Websites' })).toBeVisible()
		await modal.getByRole('button', { name: 'Close' }).click()

		// The published URL arrives in the background, no modal involved
		await expect(getToast(page, 'success', publishedUrl))
			.toContainText(`Website "My website" of collective "${collective.data.name}" published at ${publishedUrl}`, { timeout: 45_000 })
	})

	test('deletes a website the publish service doesn\'t know right away', async ({ collective, navigation, page }) => {
		const staticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [collective.getRootPage().data.id],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
			isInProgress: false,
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([staticSite]) })
		})
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'DELETE') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([]) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Websites' }) })
		const confirmation = page.getByRole('dialog', { name: 'Delete website "Published website"?' })

		await expect(await websiteAction(page, staticSite.title, 'Open website')).toBeVisible()
		await expect(page.getByRole('menuitem', { name: 'Copy link' })).toBeVisible()
		await page.getByRole('menuitem', { name: 'Delete website' }).click()
		await expect(confirmation).toContainText(`${staticSite.publishedUrl} will stop working.`)
		await expect(confirmation).toContainText('The pages in this collective are not affected.')

		// Cancelling keeps the website
		await confirmation.getByRole('button', { name: 'Cancel' }).click()
		await expect(confirmation).toHaveCount(0)
		await expect(websiteCard(page, staticSite.title)).toBeVisible()

		// An up to date website offers no update
		await expect(websiteCard(page, staticSite.title).getByRole('button', { name: /^(Update|Publish) website/ })).toHaveCount(0)

		await (await websiteAction(page, staticSite.title, 'Delete website')).click()
		await confirmation.getByRole('button', { name: 'Delete website' }).click()
		await expect(confirmation).toHaveCount(0)
		await expect(getToast(page, 'success', `Website "Published website" of collective "${collective.data.name}" deleted`)).toBeVisible()
		await expect(modal.getByRole('listitem').filter({ hasText: staticSite.title })).toHaveCount(0)
	})

	test('shows a website the publish service deletes as being deleted', async ({ collective, navigation, page }) => {
		const staticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [collective.getRootPage().data.id],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
			isInProgress: false,
		}
		const deletingStaticSite = { ...staticSite, status: 'deleting', isInProgress: true }
		let deleted = false
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([deleted ? deletingStaticSite : staticSite]) })
		})
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'DELETE') {
				return route.fallback()
			}
			deleted = true
			await route.fulfill({ status: 202, json: ocsResponse(deletingStaticSite) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const modal = page.getByRole('dialog').filter({ has: page.getByRole('heading', { name: 'Websites' }) })
		const confirmation = page.getByRole('dialog', { name: 'Delete website "Published website"?' })

		await (await websiteAction(page, staticSite.title, 'Delete website')).click()
		await confirmation.getByRole('button', { name: 'Delete website' }).click()

		await expect(getToast(page, 'success', 'Website deletion started')).toBeVisible()
		await expect(websiteCard(page, staticSite.title)).toContainText('Deleting')
		await expect(modal.getByRole('button', { name: 'New website' })).toBeDisabled()
		// The website stays online until the publish service deleted it
		await expect(await websiteAction(page, staticSite.title, 'Open website')).toBeVisible()
		await expect(page.getByRole('menuitem', { name: 'Delete website' })).toBeDisabled()
	})

	test('keeps the confirmation open if the website could not be deleted', async ({ collective, navigation, page }) => {
		const staticSite = {
			id: 1,
			title: 'Never published website',
			slug: 'never-published-website',
			selectedPageIds: [collective.getRootPage().data.id],
			publishedUrl: null,
			status: 'failed',
			isInProgress: false,
			lastError: 'generator_failed',
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([staticSite]) })
		})
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'DELETE') {
				return route.fallback()
			}
			await route.fulfill({
				status: 423,
				json: { ocs: { meta: { status: 'failure', statuscode: 423, message: 'The publish service is still processing a previous request for this website. Please try again in a few minutes.' }, data: [] } },
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const confirmation = page.getByRole('dialog', { name: 'Delete website "Never published website"?' })

		// A website that never got published has nothing to open
		await (await websiteAction(page, staticSite.title, 'Delete website')).click()
		await expect(page.getByRole('menuitem', { name: 'Open website' })).toHaveCount(0)
		await expect(confirmation).toContainText('The settings of the website are deleted.')
		await confirmation.getByRole('button', { name: 'Delete website' }).click()

		// Client errors are shown as warning toast with role status
		await expect(page.getByRole('status').filter({ hasText: 'The publish service is still processing a previous request for this website.' })).toBeVisible()
		await expect(confirmation).toBeVisible()
		await expect(confirmation.getByRole('button', { name: 'Delete website' })).toBeEnabled()
	})

	test('closes the confirmation if the publish service may have got the request although it wasn\'t reachable', async ({ collective, navigation, page }) => {
		const staticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [collective.getRootPage().data.id],
			publishedUrl: 'https://example.org/published-website',
			status: 'published',
			isInProgress: false,
		}
		let requested = false
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			// The backend keeps the website deleting, the publish service may delete it anyway
			await route.fulfill({ json: ocsResponse([requested ? { ...staticSite, status: 'deleting', isInProgress: true } : staticSite]) })
		})
		await page.route(`**/static-sites/${staticSite.id}`, async (route) => {
			if (route.request().method() !== 'DELETE') {
				return route.fallback()
			}
			requested = true
			await route.fulfill({
				status: 503,
				json: { ocs: { meta: { status: 'failure', statuscode: 503, message: 'The publish service is currently not available. Please try again later.' }, data: [] } },
			})
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const confirmation = page.getByRole('dialog', { name: 'Delete website "Published website"?' })

		await (await websiteAction(page, staticSite.title, 'Delete website')).click()
		// The web address is emphasized
		await expect(confirmation.locator('strong')).toHaveText(staticSite.publishedUrl)
		await confirmation.getByRole('button', { name: 'Delete website' }).click()

		await expect(getToast(page, 'error', 'The publish service is currently not available. Please try again later.')).toBeVisible()
		await expect(confirmation).toHaveCount(0)
		await expect(websiteCard(page, staticSite.title)).toContainText('Deleting')
	})

	test('offers to retry a failed deletion', async ({ collective, navigation, page }) => {
		const staticSite = {
			id: 1,
			title: 'Published website',
			slug: 'published-website',
			selectedPageIds: [collective.getRootPage().data.id],
			publishedUrl: 'https://example.org/published-website',
			status: 'failed',
			isInProgress: false,
			lastError: 'generator_delete_failed',
		}
		await page.route('**/static-sites', async (route) => {
			if (route.request().method() !== 'GET') {
				return route.fallback()
			}
			await route.fulfill({ json: ocsResponse([staticSite]) })
		})

		await collective.openCollective()
		await navigation.clickCollectiveMenu(collective.data.name, 'Publish website')
		const card = websiteCard(page, staticSite.title)

		await expect(card).toContainText('Live · delete failed')
		await expect(card).toContainText('The publish service could not delete the website. Please try again later.')
		await expect(card).toContainText('The website is still online.')
		await card.getByRole('button', { name: 'Retry delete' }).click()
		await expect(page.getByRole('dialog', { name: 'Delete website "Published website"?' })).toBeVisible()
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
