/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'

export default {
	computed: {
		...mapState(useRootStore, [
			'isPublic',
			'shareTokenParam',
		]),
	},

	methods: {
		/**
		 * Get markdown content of page
		 *
		 * @param {string} davUrl URL to fetch page via DAV
		 */
		async fetchPageContent(davUrl) {
			// Add `timestamp` as cache buster param
			const axiosConfig = {
				params: {
					timestamp: Math.floor(Date.now() / 1000),
				},
			}
			// Authenticate via share token for public shares
			if (this.isPublic) {
				axiosConfig.auth = {
					username: this.shareTokenParam,
				}
			}

			try {
				const content = await axios.get(davUrl, axiosConfig)
				// content.data will attempt to parse as json, but we want the raw text.
				return content.request.responseText
			} catch (e) {
				console.error('Failed to fetch content of page', e)
			}
		},
	},
}
