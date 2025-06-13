/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import * as settings from '../apis/collectives/settings.js'

export const useSettingsStore = defineStore('settings', {
	state: () => ({
		collectivesFolder: '',
	}),

	actions: {
		/**
		 * Get collectives folder setting for user
		 */
		async getCollectivesFolder() {
			const response = await settings.getCollectivesFolder()
			this.collectivesFolder = response.data.ocs.data.user_folder
		},

		/**
		 * Update collectives folder setting for user
		 *
		 * @param {string} collectivesFolder path to collectives folder
		 */
		async updateCollectivesFolder(collectivesFolder) {
			const response = await settings.setCollectivesFolder(collectivesFolder)
			this.collectivesFolder = response.data.ocs.data.user_folder
		},
	},
})
