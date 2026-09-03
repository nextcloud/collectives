/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { describe, expect, it, vi } from 'vitest'
import {
	assertVersionComparisonFixtureName,
	createVersionComparisonAccount,
	deleteVersionComparisonUser,
	provisionVersionComparisonUser,
} from '../../../playwright/support/helpers/versionComparisonFixtures.ts'

describe('version comparison persistent-stack fixtures', () => {
	it.each([
		'owner',
		'vc-owner',
		'vc-e2e',
		'other-vc-e2e-owner',
	])('rejects non-namespaced fixture mutations for %s', (name) => {
		expect(() => assertVersionComparisonFixtureName(name)).toThrow('vc-e2e-')
	})

	it('creates a namespaced account with an opaque password', () => {
		const account = createVersionComparisonAccount('pw-owner', 7, () => 'opaque-secret')

		expect(account).toEqual({
			userId: 'vc-e2e-pw-owner-7',
			password: 'VersionComparison-opaque-secret-Strong',
			language: 'en',
		})
	})

	it('keeps the longest browser worker account within the server limit', () => {
		const account = createVersionComparisonAccount('pw-owner-12345678-comparison-viewer-chromium', 0, () => 'opaque-secret')

		expect(account.userId.length).toBeLessThanOrEqual(64)
	})

	it('provisions only an exact namespaced user with password-from-env', async () => {
		const runOcc = vi.fn().mockResolvedValue({ exitCode: 0 })
		const account = createVersionComparisonAccount('owner', 'cypress', () => 'opaque-secret')

		await provisionVersionComparisonUser(account, runOcc)

		expect(runOcc).toHaveBeenNthCalledWith(1, ['user:delete', account.userId], { failOnError: false })
		expect(runOcc).toHaveBeenNthCalledWith(2, [
			'user:add',
			'--password-from-env',
			`--display-name=${account.userId}`,
			account.userId,
		], { env: ['OC_PASS=VersionComparison-opaque-secret-Strong'] })
	})

	it('fails closed before provisioning a non-namespaced user', async () => {
		const runOcc = vi.fn()

		await expect(provisionVersionComparisonUser({
			userId: 'developer',
			password: 'opaque-secret',
			language: 'en',
		}, runOcc)).rejects.toThrow('vc-e2e-')
		expect(runOcc).not.toHaveBeenCalled()
	})

	it('deletes only the exact namespaced user and surfaces cleanup failures', async () => {
		const runOcc = vi.fn().mockResolvedValue({ exitCode: 0 })

		await deleteVersionComparisonUser('vc-e2e-owner-cypress', runOcc)

		expect(runOcc).toHaveBeenCalledExactlyOnceWith(['user:delete', 'vc-e2e-owner-cypress'])
	})

	it('fails closed before deleting a non-namespaced user', async () => {
		const runOcc = vi.fn()

		await expect(deleteVersionComparisonUser('developer', runOcc)).rejects.toThrow('vc-e2e-')
		expect(runOcc).not.toHaveBeenCalled()
	})
})
