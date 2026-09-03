/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, vi } from 'vitest'
import {
	ComparisonSnapshotEncodingError,
	ComparisonSnapshotLimitError,
	loadBoundedSnapshotBody,
	loadBoundedSnapshotPreview,
	loadVersionComparisonSnapshots,
	VERSION_COMPARISON_LIMITS,
} from '../../util/versionComparison.js'

afterEach(() => vi.unstubAllGlobals())

function responseWithChunks(chunks, { status = 200 } = {}) {
	const cancel = vi.fn()
	let index = 0
	return {
		cancel,
		response: {
			ok: status >= 200 && status < 300,
			status,
			body: {
				getReader: () => ({
					cancel,
					read: vi.fn(async () => index < chunks.length
						? { done: false, value: chunks[index++] }
						: { done: true }),
				}),
			},
		},
	}
}

describe('bounded snapshot body acquisition', () => {
	it.each([
		['complete body', loadBoundedSnapshotBody],
		['bounded preview', loadBoundedSnapshotPreview],
	])('cache-busts the current %s but keeps historical snapshots cacheable', async (_name, load) => {
		vi.stubGlobal('window', { location: { href: 'https://nextcloud.local/apps/collectives/Atlas/Page' } })
		const currentResponse = responseWithChunks([new TextEncoder().encode('fresh')]).response
		const historicalResponse = responseWithChunks([new TextEncoder().encode('old')]).response
		const fetchCurrent = vi.fn().mockResolvedValue(currentResponse)
		const fetchHistorical = vi.fn().mockResolvedValue(historicalResponse)

		await load({ kind: 'current', size: 5, url: '/current?file=7' }, fetchCurrent)
		await load({ kind: 'historical', size: 3, url: '/historical?file=7' }, fetchHistorical)

		const currentUrl = new URL(fetchCurrent.mock.calls[0][0])
		expect(currentUrl.pathname).toBe('/current')
		expect(currentUrl.searchParams.get('file')).toBe('7')
		expect(currentUrl.searchParams.get('timestamp')).toMatch(/^\d{13}$/)
		expect(fetchHistorical.mock.calls[0][0]).toBe('/historical?file=7')
	})

	it('AUD-08 cancels an incorrectly advertised body at the byte ceiling', async () => {
		const first = new Uint8Array(VERSION_COMPARISON_LIMITS.maximumSnapshotBytes)
		const overflow = new Uint8Array([0x41])
		const { response, cancel } = responseWithChunks([first, overflow])
		const fetchSnapshot = vi.fn().mockResolvedValue(response)
		const snapshot = { kind: 'historical', size: 1, url: '/snapshot' }

		await expect(loadBoundedSnapshotBody(snapshot, fetchSnapshot))
			.rejects.toEqual(expect.objectContaining({
				name: 'ComparisonSnapshotLimitError',
				snapshots: [snapshot],
			}))
		expect(cancel).toHaveBeenCalledOnce()
		expect(fetchSnapshot).toHaveBeenCalledWith('/snapshot', expect.objectContaining({
			credentials: 'same-origin',
		}))
	})

	it('accepts a streamed body exactly at the byte ceiling', async () => {
		const bytes = new TextEncoder().encode('bounded')
		const { response, cancel } = responseWithChunks([bytes])

		await expect(loadBoundedSnapshotBody(
			{ kind: 'historical', size: bytes.byteLength, url: '/snapshot' },
			vi.fn().mockResolvedValue(response),
		)).resolves.toEqual({
			byteLength: bytes.byteLength,
			content: 'bounded',
		})
		expect(cancel).not.toHaveBeenCalled()
	})

	it('fails closed when a complete snapshot contains malformed UTF-8', async () => {
		const { response, cancel } = responseWithChunks([new Uint8Array([0x66, 0x80, 0x6f])])

		await expect(loadBoundedSnapshotBody(
			{ kind: 'historical', size: 3, url: '/snapshot' },
			vi.fn().mockResolvedValue(response),
		)).rejects.toBeInstanceOf(ComparisonSnapshotEncodingError)
		expect(cancel).toHaveBeenCalledOnce()
	})

	it('preserves an HTTP status for existing permission and expiry classification', async () => {
		const { response } = responseWithChunks([], { status: 403 })

		await expect(loadBoundedSnapshotBody(
			{ kind: 'historical', size: 1, url: '/snapshot' },
			vi.fn().mockResolvedValue(response),
		)).rejects.toEqual(expect.objectContaining({ response: { status: 403 } }))
	})

	it('uses the caller cancellation signal', async () => {
		const controller = new AbortController()
		const { response } = responseWithChunks([new Uint8Array()])
		const fetchSnapshot = vi.fn().mockResolvedValue(response)

		await loadBoundedSnapshotBody(
			{ kind: 'historical', size: 0, url: '/snapshot' },
			fetchSnapshot,
			controller.signal,
		)
		expect(fetchSnapshot).toHaveBeenCalledWith('/snapshot', expect.objectContaining({
			signal: controller.signal,
		}))
	})

	it('unwraps measured bodies through atomic pair loading', async () => {
		const earlier = { kind: 'historical', size: 1, url: '/earlier' }
		const later = { kind: 'current', size: 1, url: '/later' }

		await expect(loadVersionComparisonSnapshots(
			{ earlier, later },
			async ({ url }) => ({ byteLength: 1, content: url }),
		)).resolves.toEqual({
			afterContent: '/later',
			beforeContent: '/earlier',
		})
	})

	it('preserves a streamed size-limit failure through atomic pair loading', async () => {
		const limited = { kind: 'historical', size: 1, url: '/limited' }
		const current = { kind: 'current', size: 1, url: '/current' }

		await expect(loadVersionComparisonSnapshots(
			{ earlier: limited, later: current },
			(snapshot) => snapshot === limited
				? Promise.reject(new ComparisonSnapshotLimitError([snapshot]))
				: Promise.resolve('current'),
		)).rejects.toEqual(expect.objectContaining({
			name: 'ComparisonSnapshotLimitError',
			snapshots: [limited],
		}))
	})
})
