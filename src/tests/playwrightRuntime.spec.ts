/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { runExec, startNextcloud } from '@nextcloud/e2e-test-server/docker'
import { execFileSync } from 'node:child_process'
import { createHash } from 'node:crypto'
import { existsSync, mkdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/e2e-test-server/docker', () => ({
	configureNextcloud: vi.fn(),
	getContainer: vi.fn(() => ({ id: 'test-container' })),
	runExec: vi.fn(),
	runOcc: vi.fn(),
	startNextcloud: vi.fn(),
	stopNextcloud: vi.fn(),
	waitOnNextcloud: vi.fn(),
}))
vi.mock('node:child_process', () => ({ execFileSync: vi.fn() }))

const commit = 'b07c97d488f7a2c44edea8b2ad3b88a85f6612bf'

beforeEach(() => {
	vi.resetModules()
	vi.resetAllMocks()
})
afterEach(() => {
	vi.unstubAllEnvs()
	vi.unstubAllGlobals()
})

describe('Playwright runtime identity', () => {
	it.each([commit, 'stable35'])('records the resolved Text commit for %s', async (ref) => {
		vi.stubEnv('PLAYWRIGHT_TEXT_REF', ref)
		vi.mocked(runExec).mockResolvedValue({ stdout: `${commit}\n`, stderr: '', exitCode: 0 })
		const { checkoutText } = await import('../../playwright/start-nextcloud-server.js')
		expect(await checkoutText()).toBe(commit)
	})

	it.each(['0'.repeat(40), '', 'not-a-commit'])('rejects an unexpected Text checkout: %s', async (resolved) => {
		vi.stubEnv('PLAYWRIGHT_TEXT_REF', commit)
		vi.mocked(runExec).mockResolvedValue({ stdout: resolved, stderr: '', exitCode: 0 })
		const { checkoutText } = await import('../../playwright/start-nextcloud-server.js')
		await expect(checkoutText()).rejects.toThrow('Text checkout resolved')
	})

	it('rejects an already reachable server before changing its runtime', async () => {
		vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }))
		const { prepareServer } = await import('../../playwright/start-nextcloud-server.js')
		await expect(prepareServer()).rejects.toThrow('explicitly verified external runtime')
		expect(startNextcloud).not.toHaveBeenCalled()
		expect(runExec).not.toHaveBeenCalled()
	})

	it.each([true, false])('requires the Source worker before replacing installed assets (worker present: %s)', async (includeWorker) => {
		let directory = ''
		vi.mocked(execFileSync).mockImplementation((command, args) => {
			if (command === 'docker' && args?.[1]?.endsWith('apps/text/.')) {
				directory = String(args[2])
				mkdirSync(join(directory, 'js'))
				mkdirSync(join(directory, 'css'))
				writeFileSync(join(directory, 'js/text-editor.mjs'), 'editor')
				writeFileSync(join(directory, 'css/text-editor.css'), 'css')
				if (includeWorker) {
					writeFileSync(join(directory, 'js/markdownSourceComparison.worker-example.worker.mjs'), 'worker')
				}
			}
			return Buffer.from('')
		})
		const { buildText } = await import('../../playwright/start-nextcloud-server.js')
		if (includeWorker) {
			const assets = await buildText()
			expect(assets).toContainEqual({ path: 'js/text-editor.mjs', sha256: createHash('sha256').update('editor').digest('hex') })
			expect(assets).toHaveLength(3)
		} else {
			await expect(buildText()).rejects.toThrow('missing its editor entry or source comparison worker')
			expect(runExec).not.toHaveBeenCalled()
		}
		expect(existsSync(directory)).toBe(false)
	})
})
