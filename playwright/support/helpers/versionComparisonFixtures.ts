/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
/* eslint-disable jsdoc/require-jsdoc */

export const VERSION_COMPARISON_FIXTURE_PREFIX = 'vc-e2e-'

export type VersionComparisonAccount = {
	userId: string
	password: string
	language: string
}

type OccOptions = {
	env?: string[]
	failOnError?: boolean
}

type OccRunner = (command: string[], options?: OccOptions) => Promise<unknown>

export function assertVersionComparisonFixtureName(name: string): void {
	if (!name.startsWith(VERSION_COMPARISON_FIXTURE_PREFIX) || name.length === VERSION_COMPARISON_FIXTURE_PREFIX.length) {
		throw new Error(`Fixture mutations require a ${VERSION_COMPARISON_FIXTURE_PREFIX} name`)
	}
}

export function createVersionComparisonAccount(
	role: string,
	suffix: string | number,
	passwordFactory: () => string,
): VersionComparisonAccount {
	const userId = `${VERSION_COMPARISON_FIXTURE_PREFIX}${role}-${suffix}`
	assertVersionComparisonFixtureName(userId)
	return {
		userId,
		password: `VersionComparison-${passwordFactory()}-Strong`,
		language: 'en',
	}
}

export async function provisionVersionComparisonUser(account: VersionComparisonAccount, runOcc: OccRunner): Promise<void> {
	assertVersionComparisonFixtureName(account.userId)
	await runOcc(['user:delete', account.userId], { failOnError: false })
	await runOcc([
		'user:add',
		'--password-from-env',
		`--display-name=${account.userId}`,
		account.userId,
	], { env: [`OC_PASS=${account.password}`] })
}

export async function deleteVersionComparisonUser(userId: string, runOcc: OccRunner): Promise<void> {
	assertVersionComparisonFixtureName(userId)
	await runOcc(['user:delete', userId])
}
