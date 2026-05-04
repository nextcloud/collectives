/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	publicTagCreate,
	publicTagDelete,
	publicTagIndex,
	publicTagUpdate,
} from '../../client/sdk.gen.js'
import { Client } from './Client.js'

type Identifier = { token: string }

export default class PublicTag extends Client<Identifier> {
	/**
	 * Get all tags in the collective
	 */
	getTags() {
		return publicTagIndex(this.options())
	}

	/**
	 * Create a new tag for the collective
	 *
	 * @param name - Name of the tag
	 * @param color - Color of the tag in hex RGB code
	 */
	createTag(name: string, color: string) {
		return publicTagCreate(this.options({}, { name, color }))
	}

	/**
	 * Update an existing tag for the collective
	 *
	 * @param id - ID of the tag to update
	 * @param name - Name of the tag
	 * @param color - Color of the tag in hex RGB code
	 */
	updateTag(id: number, name: string, color: string) {
		return publicTagUpdate(this.options({ id }, { name, color }))
	}

	/**
	 * Delete a tag for the collective
	 *
	 * @param id - ID of the tag to update
	 */
	deleteTag(id: number) {
		return publicTagDelete(this.options({ id }))
	}
}
