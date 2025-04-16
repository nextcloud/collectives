/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { templatesUrl } from './urls.js'

/**
 * Get all templates in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 */
export function getTemplates(context) {
	return axios.get(templatesUrl(context))
}

/**
 * Create a new template in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {object} template - properties of the new template
 */
export function createTemplate(context, template) {
	return axios.post(
		templatesUrl(context, template.parentId),
		template,
	)
}

/**
 * Rename a template in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} templateId - ID of the template to rename
 * @param {string} title - New title for the template
 */
export function renameTemplate(context, templateId, title) {
	return axios.put(
		templatesUrl(context, templateId),
		{ title },
	)
}

/**
 * Set emoji for a template
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} templateId - ID of the template to update
 * @param {string} emoji - New emoji for the template
 */
export function setTemplateEmoji(context, templateId, emoji) {
	return axios.put(
		templatesUrl(context, templateId, 'emoji'),
		{ emoji },
	)
}

/**
 * Delete a template in the given context (collective or public share)
 *
 * @param {object} context - either the current collective or a share context
 * @param {number} templateId - ID of the template to delete
 */
export function deleteTemplate(context, templateId) {
	return axios.delete(templatesUrl(context, templateId))
}
