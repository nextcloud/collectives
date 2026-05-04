/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { sessionClose, sessionCreate, sessionSync } from '../../client/sdk.gen.ts'
import { defaultOptions, path } from './defaultOptions.ts'

/**
 * Create a new session for the current user
 *
 * @param collectiveId - ID of the collective
 */
export function createSession(collectiveId: number) {
	return sessionCreate({
		...defaultOptions,
		path: { ...path, collectiveId },
	})
}

/**
 * Update an existing session for the current user
 *
 * @param collectiveId - ID of the collective
 * @param token - Session token
 */
export function updateSession(collectiveId: number, token: string) {
	return sessionSync({
		...defaultOptions,
		path: { ...path, collectiveId },
		body: { token },
	})
}

/**
 * Close a session for the current user
 *
 * @param collectiveId - ID of the collective
 * @param token - Session token
 */
export function closeSession(collectiveId: number, token: string) {
	return sessionClose({
		...defaultOptions,
		path: { ...path, collectiveId },
		query: { token },
	})
}
