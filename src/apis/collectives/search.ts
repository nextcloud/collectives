/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { apiUrl } from './urls.js'

/**
 * Search pages across collectives
 *
 * @param query - the search query
 */
export function searchPages(query: string) {
	return axios.get(apiUrl('v1.0', 'collectives', 'search'), {
		params: { query },
	})
}

/**
 * Get recent pages across collectives
 */
export function getRecentPages() {
	return axios.get(apiUrl('v1.0', 'collectives', 'search', 'recent'))
}
