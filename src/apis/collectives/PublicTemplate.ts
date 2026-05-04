/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import {
	publicTemplateIndex,
} from '../../client/sdk.gen.js'
import { Client } from './Client.js'

type Identifier = { token: string }

export default class PublicTemplate extends Client<Identifier> {
	/**
	 * Get all templates in the collective
	 */
	getTemplates() {
		return publicTemplateIndex(this.options())
	}
}
