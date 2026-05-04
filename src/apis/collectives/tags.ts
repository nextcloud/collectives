/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { Context } from './Client.ts'

import { clientForContextFactory } from './Client.ts'
import PublicTag from './PublicTag.ts'
import Tag from './Tag.ts'

const tagClient = clientForContextFactory({ forCollective: Tag, forShare: PublicTag })

/**
 * Get all tags in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 */
export function getTags(context: Context) {
	return tagClient(context).getTags()
}

/**
 * Create a new tag for the collective
 *
 * @param context - either the current collective or a share context
 * @param name - Name of the tag
 * @param color - Color of the tag in hex RGB code
 */
export function createTag(context: Context, name: string, color: string) {
	return tagClient(context).createTag(name, color)
}

/**
 * Update an existing tag for the collective
 *
 * @param context - either the current collective or a share context
 * @param id - ID of the tag to update
 * @param name - Name of the tag
 * @param color - Color of the tag in hex RGB code
 */
export function updateTag(context: Context, id: number, name: string, color: string) {
	return tagClient(context).updateTag(id, name, color)
}

/**
 * Delete a tag for the collective
 *
 * @param context - either the current collective or a share context
 * @param id - ID of the tag to update
 */
export function deleteTag(context: Context, id: number) {
	return tagClient(context).deleteTag(id)
}
