/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * URL for the collective tags API
 *
 * @param {object} context - either the current collective or a share context
 * @param {Array} parts - URL parts to append - will be joined with `/`
 */
function tagApiUrl(context, ...parts) {
	return context.isPublic
		? apiUrl('v1.0', 'p', 'collectives', context.shareTokenParam, 'tags', ...parts)
		: apiUrl('v1.0', 'collectives', context.collectiveId, 'tags', ...parts)
}

/**
 * Get all tags in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 */
export function getTags(context) {
	return axios.get(tagApiUrl(context))
}

/**
 * Create a new tag for the collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {string} name - Name of the tag
 * @param {string} color - Color of the tag in hex RGB code
 */
export function createTag(context, name, color) {
	return axios.post(tagApiUrl(context), { name, color })
}

/**
 * Update an existing tag for the collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} id - ID of the tag to update
 * @param {string} name - Name of the tag
 * @param {string} color - Color of the tag in hex RGB code
 */
export function updateTag(context, id, name, color) {
	return axios.put(tagApiUrl(context, id), { name, color })
}

/**
 * Delete a tag for the collective
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} id - ID of the tag to update
 */
export function deleteTag(context, id) {
	return axios.delete(tagApiUrl(context, id))
}
