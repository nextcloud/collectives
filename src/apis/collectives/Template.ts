/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { TemplateCreateData } from '../../client/types.gen.ts'

import { templateCreate, templateDelete, templateIndex, templateRename, templateSetEmoji } from '../../client/sdk.gen.js'
import { Client } from './Client.js'

type Identifier = { collectiveId: number }

export default class Template extends Client<Identifier> {
	/**
	 * Get all templates in the collective
	 */
	getTemplates() {
		return templateIndex(this.options())
	}

	/**
	 * Create a new template for the collective
	 *
	 * @param body - attributes of the new template
	 * @param body.title - Name of the template
	 * @param body.parentId - id of the parent page
	 */
	createTemplate(body: TemplateCreateData['body']) {
		return templateCreate(this.options({ id: body.parentId.toString() }, body))
	}

	/**
	 * Rename an existing template for the collective
	 *
	 * @param id - ID of the template to update
	 * @param title - new title  of the template
	 */
	renameTemplate(id: number, title: string) {
		return templateRename(this.options({ id }, { title }))
	}

	/**
	 * Set the emoji for an existing template for the collective
	 *
	 * @param id - ID of the template to update
	 * @param emoji - Emoji to use
	 */
	setEmoji(id: number, emoji: string) {
		return templateSetEmoji(this.options({ id }, { emoji }))
	}

	/**
	 * Delete a template for the collective
	 *
	 * @param id - ID of the template to update
	 */
	deleteTemplate(id: number) {
		return templateDelete(this.options({ id }))
	}
}
