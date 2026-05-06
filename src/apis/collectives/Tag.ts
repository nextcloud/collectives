/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { tagCreate, tagDelete, tagIndex, tagUpdate } from '../../client/sdk.gen.ts'
import { Client } from './Client.ts'

type Identifier = { collectiveId: number }

export default class Tag extends Client<Identifier> {
	/**
	 * Get all tags in the collective
	 */
	getTags() {
		return tagIndex(this.options())
	}

	/**
	 * Create a new tag for the collective
	 *
	 * @param name - Name of the tag
	 * @param color - Color of the tag in hex RGB code
	 */
	createTag(name: string, color: string) {
		return tagCreate(this.options({}, { name, color }))
	}

	/**
	 * Update an existing tag for the collective
	 *
	 * @param id - ID of the tag to update
	 * @param name - Name of the tag
	 * @param color - Color of the tag in hex RGB code
	 */
	updateTag(id: number, name: string, color: string) {
		return tagUpdate(this.options({ id }, { name, color }))
	}

	/**
	 * Delete a tag for the collective
	 *
	 * @param id - ID of the tag to update
	 */
	deleteTag(id: number) {
		return tagDelete(this.options({ id }))
	}
}
