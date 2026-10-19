/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { canCompare, getViewer } from '@nextcloud/viewer'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { compareVersions } from '../../util/viewer.ts'

vi.mock('@nextcloud/auth', () => ({ getCurrentUser: () => ({ uid: 'alice' }) }))
vi.mock('@nextcloud/files', () => ({
	File: class {
		constructor(options: object) {
			Object.assign(this, options)
		}
	},
	Permission: { READ: 1 },
}))
vi.mock('@nextcloud/files/dav', () => ({ getRootPath: () => '/files/alice' }))
vi.mock('@nextcloud/viewer', () => ({
	canCompare: vi.fn(),
	canView: vi.fn(),
	getViewer: vi.fn(),
}))

const current = {
	fileId: 7,
	filename: '/files/alice/Collectives/Garden/Page.md',
	source: 'https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/Page.md',
	mime: 'text/markdown',
	size: 20,
	mtime: 2_000_000,
	etag: 'current',
}

const version = {
	...current,
	filename: '/versions/alice/versions/7/1000',
	source: 'https://cloud.example/remote.php/dav/versions/alice/versions/7/1000',
	mtime: 1_000_000,
	etag: 'old',
}

describe('compareVersions', () => {
	afterEach(() => {
		vi.clearAllMocks()
		vi.unstubAllGlobals()
	})

	it('compares in the viewer library', async () => {
		const compare = vi.fn()
		vi.mocked(canCompare).mockReturnValue(true)
		vi.mocked(getViewer).mockReturnValue({ compare } as never)

		await compareVersions(current, version)

		expect(compare).toHaveBeenCalledWith(
			expect.objectContaining({ source: current.source }),
			expect.objectContaining({ source: version.source, root: '/versions/alice' }),
		)
	})

	it('passes decoded sources to the viewer library', async () => {
		const compare = vi.fn()
		vi.mocked(canCompare).mockReturnValue(true)
		vi.mocked(getViewer).mockReturnValue({ compare } as never)
		const source = 'https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/My%20page%20%231.md'

		await compareVersions({ ...current, source }, version)

		expect(compare).toHaveBeenCalledWith(
			expect.objectContaining({ source: 'https://cloud.example/remote.php/dav/files/alice/Collectives/Garden/My page #1.md' }),
			expect.anything(),
		)
	})

	it('uses the earlier version as base regardless of the order', async () => {
		const compare = vi.fn()
		vi.mocked(canCompare).mockReturnValue(true)
		vi.mocked(getViewer).mockReturnValue({ compare } as never)

		await compareVersions(version, current)

		expect(compare).toHaveBeenCalledWith(
			expect.objectContaining({ source: current.source }),
			expect.objectContaining({ source: version.source }),
		)
	})

	it('falls back to the Viewer app', async () => {
		const compare = vi.fn()
		vi.mocked(canCompare).mockReturnValue(false)
		vi.stubGlobal('window', { OCA: { Viewer: { registerHandler: vi.fn(), compare } } })

		await compareVersions(current, version)

		expect(getViewer).not.toHaveBeenCalled()
		expect(compare).toHaveBeenCalledWith(current, version)
	})
})
