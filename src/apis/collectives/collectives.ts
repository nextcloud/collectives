/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { createClient } from '../../client/client/client.gen.ts'
import {
	type CollectiveCreateData,
	type CollectiveUpdateData,

	collectiveCreate,
	collectiveEditLevel,
	collectiveIndex,
	collectivePageMode,
	collectiveShareLevel,
	collectiveTrash,
	collectiveUpdate,
	publicCollectiveGet,
	trashDelete,
	trashIndex,
	trashRestore,
} from '../../client/index.ts'

const client = createClient({ axios })
const headers = { 'OCS-APIRequest': true }
const path = Object.freeze({ apiVersion: '1.0' })
const defaultOptions = Object.freeze({
	client,
	headers,
	path,
	throwOnError: true,
})

/**
 * Get all active (i.e. not trashed) collectives for the current user
 */
export function getCollectives() {
	return collectiveIndex(defaultOptions)
}

/**
 * Get the shared collective for a given share token.
 *
 * @param token authentication token from the share
 */
export function getSharedCollective(token: string) {
	return publicCollectiveGet({
		...defaultOptions,
		path: { ...path, token },
	})
}

/**
 * Get all trashed collectives for the current user
 */
export function getTrashCollectives() {
	return trashIndex(defaultOptions)
}

/**
 * Create a new collective with the given properties.
 *
 * @param collective - properties for the new collective
 */
export function newCollective(collective: CollectiveCreateData['body']) {
	return collectiveCreate({
		...defaultOptions,
		body: collective,
	})
}

/**
 * Trash the collective with the given id
 *
 * @param collectiveId - Id of the collective to trash.
 */
export function trashCollective(collectiveId: number) {
	return collectiveTrash({
		...defaultOptions,
		path: { ...path, id: collectiveId },
	})
}

/**
 * Delete the collective with the given id.
 *
 * @param collectiveId - id of the collective to delete
 * @param removeCircle - also remove the circle if true
 */
export function deleteCollective(collectiveId: number, removeCircle: boolean) {
	return trashDelete({
		...defaultOptions,
		path: { ...path, id: collectiveId },
		query: { circle: removeCircle },
	})
}

/**
 * Restore a collective with the given id from trash
 *
 * @param collectiveId Id of the colletive to be restored
 */
export function restoreCollective(collectiveId: number) {
	return trashRestore({
		...defaultOptions,
		path: { ...path, id: collectiveId },
	})
}

/**
 * Update a collective with the given properties
 *
 * @param collective Properties for the collective
 */
export function updateCollective(collective: CollectiveUpdateData['body'] & { id: number }) {
	return collectiveUpdate({
		...defaultOptions,
		path: { ...path, id: collective.id },
		body: collective,
	})
}

/**
 * Set the permission level required for editing.
 *
 * @param collectiveId - id of the collective to update
 * @param level - required level for editing
 */
export function updateCollectiveEditPermissions(collectiveId: number, level: number) {
	return collectiveEditLevel({
		...defaultOptions,
		path: { ...path, id: collectiveId },
		body: { level },
	})
}

/**
 * Set the permission level required for sharing.
 *
 * @param collectiveId - id of the collective to update
 * @param level - required level for sharing
 */
export function updateCollectiveSharePermissions(collectiveId: number, level: number) {
	return collectiveShareLevel({
		...defaultOptions,
		path: { ...path, id: collectiveId },
		body: { level },
	})
}

/**
 * Set the edit mode for the given collective
 *
 * @param collectiveId - id of the collective to update
 * @param mode - pageMode to use.
 *
 * Possible modes: pageModes.MODE_PREVIEW or pageModes.MODE_EDIT
 */
export function updateCollectivePageMode(collectiveId: number, mode: number) {
	return collectivePageMode({
		...defaultOptions,
		path: { ...path, id: collectiveId },
		body: { mode },
	})
}
