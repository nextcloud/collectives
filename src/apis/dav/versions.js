/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCurrentUser } from '@nextcloud/auth'
import { getClient } from '@nextcloud/files/dav'
import * as davRequests from './davRequests.js'

/**
 * Get DAV path to versions of a file
 *
 * @param {number} fileId - ID of the file
 * @return {string}
 */
function getVersionsPath(fileId) {
	const user = getCurrentUser().uid
	return `/versions/${user}/versions/${fileId}`
}

/**
 * Get DAV path to a particular version of a file
 *
 * @param {number} fileId - ID of the file
 * @param {string} fileVersion - Version to restore
 * @return {string}
 */
function getVersionPath(fileId, fileVersion) {
	return `${getVersionsPath(fileId)}/${fileVersion}`
}

/**
 * Get versions of a file
 *
 * @param {number} fileId - ID of the file
 */
export function getVersions(fileId) {
	const client = getClient()

	return client.getDirectoryContents(getVersionsPath(fileId), {
		data: davRequests.listVersions(),
		details: true,
	})
}

/**
 * Restore version of a file
 *
 * @param {number} fileId - ID of the file
 * @param {string} fileVersion - Version to restore
 */
export function restoreVersion(fileId, fileVersion) {
	const client = getClient()
	const user = getCurrentUser().uid

	return client.moveFile(
		getVersionPath(fileId, fileVersion),
		`/versions/${user}/restore/target`,
	)
}

/**
 * Set label of a file version
 *
 * @param {number} fileId - ID of the file
 * @param {string} fileVersion - Version to restore
 * @param {string} label - Label to set
 */
export function setVersionLabel(fileId, fileVersion, label) {
	const client = getClient()
	return client.customRequest(
		getVersionPath(fileId, fileVersion),
		{
			method: 'PROPPATCH',
			data: davRequests.setLabel(label),
		},
	)
}

/**
 * Delete version of a file
 *
 * @param {number} fileId - ID of the file
 * @param {string} fileVersion - Version to restore
 */
export function deleteVersion(fileId, fileVersion) {
	const client = getClient()
	return client.deleteFile(getVersionPath(fileId, fileVersion))
}
