/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * Render a sample static site with Hugo and store it in the user's files.
 *
 * @param {string} title - Title shown on the generated site
 */
export function generateStaticSite(title) {
	return axios.post(apiUrl('v1.0', 'staticsite'), { title })
}
