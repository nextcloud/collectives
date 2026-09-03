/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { afterEach, describe, expect, it, vi } from 'vitest'

const originalBaseURL = process.env.baseURL
const originalSemanticE2E = process.env.COLLECTIVES_SEMANTIC_E2E

afterEach(() => {
	if (originalBaseURL === undefined) {
		delete process.env.baseURL
	} else {
		process.env.baseURL = originalBaseURL
	}
	if (originalSemanticE2E === undefined) {
		delete process.env.COLLECTIVES_SEMANTIC_E2E
	} else {
		process.env.COLLECTIVES_SEMANTIC_E2E = originalSemanticE2E
	}
})

async function loadConfig(baseURL: string | undefined, semanticE2E = false) {
	if (baseURL === undefined) {
		delete process.env.baseURL
	} else {
		process.env.baseURL = baseURL
	}
	process.env.COLLECTIVES_SEMANTIC_E2E = semanticE2E ? '1' : '0'
	vi.resetModules()
	return (await import('../../playwright.config.ts')).default
}

describe('Playwright server selection', () => {
	it.each([undefined, ''])('uses the managed server for %s baseURL', async (baseURL) => {
		const config = await loadConfig(baseURL)

		expect(config.use?.baseURL).toBe('http://localhost:8089/index.php/')
		expect(config.webServer).toBeDefined()
	})

	it('uses a non-empty external baseURL without the managed server', async () => {
		const config = await loadConfig('https://nextcloud.local/index.php/')

		expect(config.use?.baseURL).toBe('https://nextcloud.local/index.php/')
		expect(config.webServer).toBeUndefined()
	})

	it('runs only the tagged Viewer fallback in the stable project', async () => {
		const config = await loadConfig(undefined)
		const project = config.projects?.find(({ name }) => name === 'comparison-viewer-chromium')

		expect(project?.grep).toEqual(/@viewer-fallback/)
		expect(config.projects?.map(({ name }) => name)).not.toContain('comparison-chromium')
	})

	it('runs semantic comparison in Chromium without the Viewer fallback', async () => {
		const config = await loadConfig(undefined, true)
		const projects = config.projects?.filter(({ name }) => name?.startsWith('comparison-') && name !== 'comparison-viewer-chromium')

		expect(projects?.map(({ name }) => name)).toEqual(['comparison-chromium'])
		expect(projects?.map(({ grepInvert }) => grepInvert)).toEqual([/@viewer-fallback/])
	})
})
