/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showError, showSuccess } from '@nextcloud/dialogs'
import { createPinia, setActivePinia } from 'pinia'
import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import * as api from '../../apis/collectives/index.js'
import { staticSitePollInterval, staticSitePollTimeout } from '../../constants.js'
import { useStaticSitesStore } from '../../stores/staticSites.js'

vi.mock('../../apis/collectives/index.js', () => ({ deleteStaticSite: vi.fn(), getStaticSites: vi.fn() }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, message, parameters = {}) => message.replace(/\{(\w+)}/g, (match, name) => parameters[name] ?? match),
}))

const publishedUrl = 'https://example.org/site'
const collective = { id: 1, name: 'Collective' }
const otherCollective = { id: 2, name: 'Other collective' }

function response(...staticSites) {
	return { data: { ocs: { data: staticSites } } }
}

function staticSite(properties = {}) {
	return { id: 1, title: 'Website', status: 'provided', isInProgress: true, publishedUrl: null, ...properties }
}

async function poll(times = 1) {
	await vi.advanceTimersByTimeAsync(staticSitePollInterval * 1000 * times)
}

describe('static sites of a collective', () => {
	beforeAll(() => {
		vi.stubGlobal('window', { OC: { getLanguage: () => 'en' } })
	})

	afterAll(() => {
		vi.unstubAllGlobals()
	})

	beforeEach(() => {
		setActivePinia(createPinia())
	})

	it('lists static sites by title, regardless of their update time', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(
			staticSite({ id: 1, title: 'Website 10', updatedAt: 300 }),
			staticSite({ id: 2, title: 'Archive', updatedAt: 100 }),
			staticSite({ id: 3, title: 'Website 2', updatedAt: 200 }),
		))
		const store = useStaticSitesStore()

		await store.getStaticSites(1)

		expect(store.staticSitesForCollective(1).map(({ id }) => id)).toEqual([2, 3, 1])
	})

	it('lists no static sites for an unknown collective', () => {
		expect(useStaticSitesStore().staticSitesForCollective(1)).toEqual([])
	})
})

describe('static sites publication watching', () => {
	beforeEach(() => {
		setActivePinia(createPinia())
		vi.restoreAllMocks()
		vi.useFakeTimers()
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('notifies about the published URL and stops polling once it arrived', async () => {
		vi.mocked(api.getStaticSites)
			.mockResolvedValueOnce(response(staticSite()))
			.mockResolvedValue(response(staticSite({ status: 'published', publishedUrl })))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await poll()
		// The publish service didn't answer yet
		expect(showSuccess).not.toHaveBeenCalled()

		await poll()
		expect(showSuccess).toHaveBeenCalledOnce()
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website" of collective "Collective" published at ${publishedUrl}`)
		expect(store.staticSitesForCollective(1)[0].publishedUrl).toBe(publishedUrl)

		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledTimes(2)
	})

	it('notifies about a republication that kept its URL', async () => {
		// An update keeps the URL of the previous publication while in progress
		vi.mocked(api.getStaticSites)
			.mockResolvedValueOnce(response(staticSite({ publishedUrl })))
			.mockResolvedValue(response(staticSite({ status: 'published', publishedUrl })))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite({ publishedUrl }))
		await poll()
		expect(showSuccess).not.toHaveBeenCalled()

		await poll()
		expect(showSuccess).toHaveBeenCalledOnce()
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website" of collective "Collective" published at ${publishedUrl}`)
	})

	it('shows the reason and stops polling if the publication failed', async () => {
		vi.mocked(api.getStaticSites)
			.mockResolvedValue(response(staticSite({ status: 'failed', isInProgress: false, lastError: 'generator_failed' })))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await poll(5)

		expect(api.getStaticSites).toHaveBeenCalledOnce()
		expect(showError).toHaveBeenCalledOnce()
		expect(showError).toHaveBeenCalledWith('Website "Website" of collective "Collective" could not be published: The publish service could not build the website. Please contact your administrator.')
		expect(showSuccess).not.toHaveBeenCalled()
	})

	it('notifies about a publication the backend considers aborted', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite({ isInProgress: false })))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await poll(5)

		expect(api.getStaticSites).toHaveBeenCalledOnce()
		expect(showError).toHaveBeenCalledOnce()
		expect(showError).toHaveBeenCalledWith('Website "Website" of collective "Collective" could not be published: The publish service didn\'t respond in time.')
		expect(showSuccess).not.toHaveBeenCalled()
	})

	it('stops polling without a toast if the publication vanished', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response())
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await poll(5)

		expect(api.getStaticSites).toHaveBeenCalledOnce()
		expect(showSuccess).not.toHaveBeenCalled()
		expect(showError).not.toHaveBeenCalled()
	})

	it('restarts for a new publication next to an already published site', async () => {
		const publishedSite = staticSite({ id: 1, title: 'Old website', status: 'published', publishedUrl })
		vi.mocked(api.getStaticSites)
			.mockResolvedValueOnce(response(publishedSite))
			.mockResolvedValue(response(
				publishedSite,
				staticSite({ id: 2, title: 'New website', status: 'published', publishedUrl: 'https://example.org/new-site' }),
			))
		const store = useStaticSitesStore()

		// A previous publication finished and stopped its polling
		store.watchPublication(collective, staticSite({ id: 1, title: 'Old website' }))
		await poll()
		expect(showSuccess).toHaveBeenCalledWith(`Website "Old website" of collective "Collective" published at ${publishedUrl}`)
		expect(store.pollIntervalId).toBeNull()

		store.watchPublication(collective, staticSite({ id: 2, title: 'New website' }))
		await poll()

		expect(showSuccess).toHaveBeenCalledTimes(2)
		expect(showSuccess).toHaveBeenLastCalledWith('Website "New website" of collective "Collective" published at https://example.org/new-site')
	})

	it('stops polling after the poll timeout although the publication is still in progress', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite()))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await vi.advanceTimersByTimeAsync(staticSitePollTimeout * 1000)
		const attempts = vi.mocked(api.getStaticSites).mock.calls.length

		expect(store.pollIntervalId).toBeNull()
		expect(attempts).toBe(staticSitePollTimeout / staticSitePollInterval)

		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledTimes(attempts)
	})

	it('watches several publications of a collective with one request per poll', async () => {
		vi.mocked(api.getStaticSites)
			.mockResolvedValueOnce(response(staticSite({ id: 1, title: 'Website A' }), staticSite({ id: 2, title: 'Website B' })))
			.mockResolvedValue(response(
				staticSite({ id: 1, title: 'Website A', status: 'published', publishedUrl }),
				staticSite({ id: 2, title: 'Website B', status: 'failed', isInProgress: false, lastError: 'generator_failed' }),
			))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite({ id: 1, title: 'Website A' }))
		store.watchPublication(collective, staticSite({ id: 2, title: 'Website B' }))
		await poll()
		expect(api.getStaticSites).toHaveBeenCalledOnce()

		await poll()
		expect(api.getStaticSites).toHaveBeenCalledTimes(2)
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website A" of collective "Collective" published at ${publishedUrl}`)
		expect(showError).toHaveBeenCalledWith('Website "Website B" of collective "Collective" could not be published: The publish service could not build the website. Please contact your administrator.')
		expect(store.pollIntervalId).toBeNull()
	})

	it('watches publications of several collectives', async () => {
		vi.mocked(api.getStaticSites).mockImplementation(async (collectiveId) => {
			return response(staticSite({ id: collectiveId * 10, title: `Website ${collectiveId}`, status: 'published', publishedUrl }))
		})
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite({ id: 10 }))
		store.watchPublication(otherCollective, staticSite({ id: 20 }))
		await poll()

		expect(api.getStaticSites).toHaveBeenCalledWith(1)
		expect(api.getStaticSites).toHaveBeenCalledWith(2)
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website 1" of collective "Collective" published at ${publishedUrl}`)
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website 2" of collective "Other collective" published at ${publishedUrl}`)
		expect(store.pollIntervalId).toBeNull()
	})

	it('restarts watching a publication that got published again', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite()))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await vi.advanceTimersByTimeAsync((staticSitePollTimeout - staticSitePollInterval) * 1000)
		store.watchPublication(collective, staticSite())
		await poll(5)

		expect(store.pollIntervalId).not.toBeNull()
		store.stopWatching()
	})

	it('stops watching all publications', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite()))
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite({ id: 1 }))
		store.watchPublication(otherCollective, staticSite({ id: 2 }))
		store.stopWatching()
		await poll(5)

		expect(api.getStaticSites).not.toHaveBeenCalled()
		expect(store.watchedRequests).toEqual({})
	})

	it('keeps polling after a failed request', async () => {
		vi.mocked(api.getStaticSites)
			.mockRejectedValueOnce(new Error('request failed'))
			.mockResolvedValue(response(staticSite({ status: 'published', publishedUrl })))
		const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await poll(2)

		expect(consoleError).toHaveBeenCalledWith('Could not load static sites for collective', expect.any(Error))
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website" of collective "Collective" published at ${publishedUrl}`)
	})

	it('stops polling if requests keep failing', async () => {
		vi.mocked(api.getStaticSites).mockRejectedValue(new Error('request failed'))
		vi.spyOn(console, 'error').mockImplementation(() => {})
		const store = useStaticSitesStore()

		store.watchPublication(collective, staticSite())
		await vi.advanceTimersByTimeAsync(staticSitePollTimeout * 1000)
		const attempts = vi.mocked(api.getStaticSites).mock.calls.length

		expect(store.pollIntervalId).toBeNull()
		expect(attempts).toBe(staticSitePollTimeout / staticSitePollInterval)

		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledTimes(attempts)
	})
})

describe('static site deletion', () => {
	const published = staticSite({ status: 'published', isInProgress: false, publishedUrl })
	const deleting = staticSite({ status: 'deleting', isInProgress: true, publishedUrl })

	beforeAll(() => {
		vi.stubGlobal('window', { OC: { getLanguage: () => 'en' } })
	})

	afterAll(() => {
		vi.unstubAllGlobals()
	})

	beforeEach(async () => {
		setActivePinia(createPinia())
		vi.resetAllMocks()
		vi.useFakeTimers()
		vi.mocked(api.getStaticSites).mockResolvedValueOnce(response(published, staticSite({ id: 2, title: 'Other website' })))
		await useStaticSitesStore().getStaticSites(collective.id)
	})

	afterEach(() => {
		vi.useRealTimers()
	})

	it('removes a static site the publish service didn\'t know right away', async () => {
		vi.mocked(api.deleteStaticSite).mockResolvedValue({ status: 200, data: { ocs: { data: [] } } })
		const store = useStaticSitesStore()

		await store.deleteStaticSite(collective, published)

		expect(api.deleteStaticSite).toHaveBeenCalledWith(1, 1)
		expect(store.staticSitesForCollective(1).map(({ id }) => id)).toEqual([2])
		expect(showSuccess).toHaveBeenCalledWith('Website "Website" of collective "Collective" deleted')
		expect(store.pollIntervalId).toBeNull()
	})

	it('waits until the publish service deleted the static site', async () => {
		vi.mocked(api.deleteStaticSite).mockResolvedValue({ status: 202, data: { ocs: { data: deleting } } })
		vi.mocked(api.getStaticSites)
			.mockResolvedValueOnce(response(deleting))
			.mockResolvedValue(response())
		const store = useStaticSitesStore()

		await store.deleteStaticSite(collective, published)
		expect(store.staticSitesForCollective(1).find(({ id }) => id === 1).status).toBe('deleting')
		expect(showSuccess).toHaveBeenCalledWith('Website deletion started')

		await poll()
		expect(showSuccess).not.toHaveBeenCalledWith('Website "Website" of collective "Collective" deleted')

		await poll()
		expect(showSuccess).toHaveBeenCalledWith('Website "Website" of collective "Collective" deleted')
		expect(store.pollIntervalId).toBeNull()
	})

	it('shows the reason if the publish service failed to delete the static site', async () => {
		vi.mocked(api.deleteStaticSite).mockResolvedValue({ status: 202, data: { ocs: { data: deleting } } })
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite({ status: 'failed', isInProgress: false, lastError: 'generator_delete_failed', publishedUrl })))
		const store = useStaticSitesStore()

		await store.deleteStaticSite(collective, published)
		await poll()

		expect(showError).toHaveBeenCalledWith('Website "Website" of collective "Collective" could not be deleted: The publish service could not delete the website. Please try again later.')
		expect(store.pollIntervalId).toBeNull()
	})

	it('keeps the static sites if the request fails', async () => {
		vi.mocked(api.deleteStaticSite).mockRejectedValue(new Error('request failed'))
		const store = useStaticSitesStore()

		await expect(store.deleteStaticSite(collective, published)).rejects.toThrow('request failed')

		expect(store.staticSitesForCollective(1)).toHaveLength(2)
		expect(showSuccess).not.toHaveBeenCalled()
	})
})
