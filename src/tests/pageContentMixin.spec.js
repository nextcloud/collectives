/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import pageContentMixin from '../mixins/pageContentMixin.js'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('../stores/root.js', () => ({ useRootStore: vi.fn() }))

const fetchContent = (url) => pageContentMixin.methods.fetchPageContent.call({}, url)

describe('DAV page content loading', () => {
	beforeEach(() => {
		vi.resetAllMocks()
	})

	it.each(['/remote.php/dav/files/alice/page.md', '/public.php/dav/files/token/page.md', '/remote.php/dav/versions/alice/12/100'])('loads raw content from %s', async (url) => {
		axios.get.mockResolvedValue({ request: { responseText: '{"markdown":true}' } })
		await expect(fetchContent(url)).resolves.toBe('{"markdown":true}')
		expect(axios.get).toHaveBeenCalledWith(url, {
			params: { timestamp: expect.any(Number) },
		})
	})

	it('preserves empty page content', async () => {
		axios.get.mockResolvedValue({ request: { responseText: '' } })
		await expect(fetchContent('/dav/page')).resolves.toBe('')
	})

	it('returns a string after a failed request instead of crashing reader initialization', async () => {
		vi.spyOn(console, 'error').mockImplementation(() => {})
		axios.get.mockRejectedValue(new Error('Forbidden'))
		await expect(fetchContent('/dav/page')).resolves.toBe('')
		vi.restoreAllMocks()
	})
})
