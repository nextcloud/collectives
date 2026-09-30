/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { showSuccess } from '@nextcloud/dialogs'
import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import * as api from '../../apis/collectives/index.js'
import { staticSitePollInterval, staticSitePublicationTimeout } from '../../constants.js'
import { useStaticSitesStore } from '../../stores/staticSites.js'

vi.mock('../../apis/collectives/index.js', () => ({ getStaticSites: vi.fn() }))
vi.mock('@nextcloud/dialogs', () => ({ showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, message, parameters = {}) => message.replace(/\{(\w+)\}/g, (match, name) => parameters[name] ?? match),
}))

const publishedUrl = 'https://example.org/site'

function response(...staticSites) {
	return { data: { ocs: { data: staticSites } } }
}

function staticSite(properties = {}) {
	return { id: 1, title: 'Website', status: 'provided', publishedUrl: null, ...properties }
}

async function poll(times = 1) {
	await vi.advanceTimersByTimeAsync(staticSitePollInterval * 1000 * times)
}

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

		store.watchPublication(1, staticSite())
		await poll()
		// The publish service didn't answer yet
		expect(showSuccess).not.toHaveBeenCalled()

		await poll()
		expect(showSuccess).toHaveBeenCalledOnce()
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website" published at ${publishedUrl}`)
		expect(store.publishedUrlForCollective(1)).toBe(publishedUrl)

		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledTimes(2)
	})

	it('notifies about a republication that got a different URL', async () => {
		vi.mocked(api.getStaticSites)
			.mockResolvedValue(response(staticSite({ status: 'published', publishedUrl: 'https://example.org/new-site' })))
		const store = useStaticSitesStore()

		store.watchPublication(1, staticSite({ status: 'published', publishedUrl }))
		await poll()

		expect(showSuccess).toHaveBeenCalledWith('Website "Website" published at https://example.org/new-site')
	})

	it('stops polling without a toast if the publication failed or vanished', async () => {
		vi.mocked(api.getStaticSites)
			.mockResolvedValueOnce(response(staticSite({ status: 'failed' })))
			.mockResolvedValue(response())
		const store = useStaticSitesStore()

		store.watchPublication(1, staticSite())
		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledTimes(1)

		store.watchPublication(2, staticSite())
		await poll(5)

		expect(api.getStaticSites).toHaveBeenCalledTimes(2)
		expect(showSuccess).not.toHaveBeenCalled()
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
		store.watchPublication(1, staticSite({ id: 1, title: 'Old website' }))
		await poll()
		expect(showSuccess).toHaveBeenCalledWith(`Website "Old website" published at ${publishedUrl}`)
		expect(store.pollIntervalId).toBeNull()

		store.watchPublication(1, staticSite({ id: 2, title: 'New website' }))
		await poll()

		expect(showSuccess).toHaveBeenCalledTimes(2)
		expect(showSuccess).toHaveBeenLastCalledWith('Website "New website" published at https://example.org/new-site')
	})

	it('notifies even if the status was not updated along with the published URL', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite({ status: 'provided', publishedUrl })))
		const store = useStaticSitesStore()

		store.watchPublication(1, staticSite())
		await poll()

		expect(showSuccess).toHaveBeenCalledWith(`Website "Website" published at ${publishedUrl}`)
		expect(store.pollIntervalId).toBeNull()
	})

	it('gives up on a publication that stays quiet for too long', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite()))
		const store = useStaticSitesStore()

		store.watchPublication(1, staticSite())
		await vi.advanceTimersByTimeAsync(staticSitePublicationTimeout * 1000)
		const attempts = vi.mocked(api.getStaticSites).mock.calls.length

		expect(store.pollIntervalId).toBeNull()
		expect(attempts).toBe(staticSitePublicationTimeout / staticSitePollInterval)

		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledTimes(attempts)
	})

	it('watches one publication at a time', async () => {
		vi.mocked(api.getStaticSites).mockResolvedValue(response(staticSite()))
		const store = useStaticSitesStore()

		store.watchPublication(1, staticSite())
		store.watchPublication(2, staticSite())
		await poll()

		expect(api.getStaticSites).toHaveBeenCalledOnce()
		expect(api.getStaticSites).toHaveBeenCalledWith(2)

		store.stopWatching()
		await poll(5)
		expect(api.getStaticSites).toHaveBeenCalledOnce()
	})

	it('keeps polling after a failed request', async () => {
		vi.mocked(api.getStaticSites)
			.mockRejectedValueOnce(new Error('request failed'))
			.mockResolvedValue(response(staticSite({ status: 'published', publishedUrl })))
		const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
		const store = useStaticSitesStore()

		store.watchPublication(1, staticSite())
		await poll(2)

		expect(consoleError).toHaveBeenCalledWith('Could not load static sites for collective', expect.any(Error))
		expect(showSuccess).toHaveBeenCalledWith(`Website "Website" published at ${publishedUrl}`)
	})
})
