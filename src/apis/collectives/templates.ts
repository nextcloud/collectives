/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { TemplateCreateData } from '../../client/types.gen.ts'
import type { CollectiveContext, Context } from './Client.ts'

import { clientForContextFactory } from './Client.ts'
import PublicTemplate from './PublicTemplate.ts'
import Template from './Template.ts'

const templateClient = clientForContextFactory({ forCollective: Template, forShare: PublicTemplate })

/**
 * Get all templates in the given context (collective or public share)
 *
 * @param context - either the current collective or a share context
 */
export function getTemplates(context: Context) {
	return templateClient(context).getTemplates()
}

/**
 * Create a new template in the given collective
 *
 * @param context - the current collective
 * @param context.collectiveId - id of the collective
 * @param context.isPublic - false, this api call only exists after login
 * @param template - properties of the new template
 */
export function createTemplate(context: CollectiveContext, template: TemplateCreateData['body']) {
	return templateClient(context).createTemplate(template)
}

/**
 * Rename a template in the given context (collective or public share)
 *
 * @param context - the current collective
 * @param templateId - ID of the template to rename
 * @param title - New title for the template
 */
export function renameTemplate(context: CollectiveContext, templateId: number, title: string) {
	return templateClient(context).renameTemplate(templateId, title)
}

/**
 * Set emoji for a template
 *
 * @param context - the current collective
 * @param templateId - ID of the template to rename
 * @param emoji - New emoji for the template
 */
export function setTemplateEmoji(context: CollectiveContext, templateId: number, emoji: string) {
	return templateClient(context).setEmoji(templateId, emoji)
}

/**
 * Delete a template in the given context (collective or public share)
 *
 * @param context - the current collective
 * @param templateId - ID of the template to rename
 */
export function deleteTemplate(context: CollectiveContext, templateId: number) {
	return templateClient(context).deleteTemplate(templateId)
}
