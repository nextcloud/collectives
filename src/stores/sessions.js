/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineStore } from 'pinia'
import * as api from '../apis/collectives/index.js'
import { useCollectivesStore } from './collectives.js'

export const useSessionsStore = defineStore('sessions', {
	state: () => ({
		session: {},
	}),

	getters: {
		hasSession: (state) => !!Object.keys(state.session).length,
	},

	actions: {
		async createSession() {
			const collectivesStore = useCollectivesStore()
			const response = await api.createSession(collectivesStore.currentCollective.id)
			this.session = { collectiveId: collectivesStore.currentCollective.id, token: response.data.ocs.data.token }
		},

		async updateSession() {
			try {
				await api.updateSession(this.session.collectiveId, this.session.token)
			} catch (e) {
				console.error('Session update failed, creating a new one', e)
				this.createSession()
			}
		},

		async closeSession() {
			await api.closeSession(this.session.collectiveId, this.session.token)
			this.session = {}
		},
	},
})
