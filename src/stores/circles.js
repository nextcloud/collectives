/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import { useLocalStorage } from '@vueuse/core'
import { defineStore } from 'pinia'
import { circlesMemberTypes } from '../constants.js'
import { sortMembersByLevelAndType } from '../util/circles.ts'
import { useCollectivesStore } from './collectives.js'
import { useRootStore } from './root.js'

const STORE_PREFIX = 'collectives/pinia/circles/'

export const useCirclesStore = defineStore('circles', {
	state: () => ({
		circles: useLocalStorage(STORE_PREFIX + 'circles', []),
		circlesMembers: useLocalStorage(STORE_PREFIX + 'circlesMembers', {}),
	}),

	getters: {
		availableCircles(state) {
			const collectivesStore = useCollectivesStore()
			return state.circles
				.filter((circle) => circle.initiator) // only circles I am a member of
				.filter((circle) => {
					const matchCircleId = (c) => {
						return (c.circleId === circle.id)
					}
					const alive = collectivesStore.collectives.find(matchCircleId)
					const trashed = collectivesStore.trashCollectives.find(matchCircleId)
					return !alive && !trashed
				})
		},

		currentCircleMembers: (state) => {
			const collectivesStore = useCollectivesStore()
			const currentCircleId = collectivesStore.currentCollective?.circleId
			return state.circlesMembers[currentCircleId]
		},

		circleMembers: (state) => (circleId) => state.circlesMembers[circleId] || [],

		currentCircleMembersSorted: (state) => state.currentCircleMembers.slice().sort(sortMembersByLevelAndType),

		circleMembersSorted: (state) => (circleId) => {
			return state.circleMembers(circleId)
				.slice()
				.sort(sortMembersByLevelAndType)
		},

		currentCircleUserMembersSorted: (state) => {
			const users = {}
			for (const member of state.currentCircleMembersSorted) {
				if (member.userType === circlesMemberTypes.TYPE_USER) {
					users[member.userId] = member.displayName
				}
			}
			return users
		},
	},

	actions: {
		deleteCircleForCollectiveFromState(collective) {
			this.circles.splice(this.circles.findIndex((c) => c.id === collective.circleId), 1)
		},

		/**
		 * Get list of all teams
		 */
		async getCircles() {
			const rootStore = useRootStore()
			if (rootStore.isPublic) {
				return
			}
			const response = await axios.get(generateOcsUrl('apps/circles/circles'))
			this.circles = response.data.ocs.data
		},

		/**
		 * Rename a team
		 *
		 * @param {object} collective the collective with circleId and name
		 */
		async renameCircle(collective) {
			const collectivesStore = useCollectivesStore()
			const response = await axios.put(
				generateOcsUrl('apps/circles/circles/' + collective.circleId + '/name'),
				{ value: collective.name },
			)
			this.circles.splice(
				this.circles.findIndex((c) => c.id === response.data.ocs.data.id),
				1,
				response.data.ocs.data,
			)

			if (collective.id === collectivesStore.currentCollective?.id) {
				// Update page list, properties like `collectivePath` might have changed
				// TODO: use pagesStore
				// await dispatch(GET_PAGES, false)
				// await dispatch(GET_TRASH_PAGES)
			}
			collectivesStore.patchCollectiveWithCircle(response.data.ocs.data)
		},

		/**
		 * Get members of a team
		 *
		 * @param {string} circleId ID of the team
		 */
		async getCircleMembers(circleId) {
			const response = await axios.get(generateOcsUrl(`apps/circles/circles/${circleId}/members?fullDetails=true`))
			this.circlesMembers[circleId] = response.data.ocs.data
		},

		/**
		 * Add a single member to a team
		 *
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the team
		 * @param {string} params.userId User ID of the member to be added
		 * @param {number} params.type Type of the member to be added
		 */
		async addMemberToCircle({ circleId, userId, type }) {
			const response = await axios.post(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members'),
				{ userId, type },
			)
			return response.data.ocs.data
		},

		/**
		 * Add multiple members to a team
		 *
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the team
		 * @param {object} params.members Object with members to be added
		 */
		async addMembersToCircle({ circleId, members }) {
			const response = await axios.post(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members/multi'),
				{ members },
			)
			return response.data.ocs.data
		},

		/**
		 * Remove a single member to a team
		 *
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the team
		 * @param {string} params.memberId Team member ID of the member to be removed
		 */
		async removeMemberFromCircle({ circleId, memberId }) {
			const response = await axios.delete(generateOcsUrl('apps/circles/circles/' + circleId + '/members/' + memberId))
			return response.data.ocs.data
		},

		/**
		 * Change a member level in a team
		 *
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the team
		 * @param {string} params.memberId Team member ID of the member to be changed
		 * @param {number} params.level Level of the member to be changed
		 */
		async changeCircleMemberLevel({ circleId, memberId, level }) {
			const response = await axios.put(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members/' + memberId + '/level'),
				{ level },
			)
			return response.data.ocs.data
		},

		/**
		 * Leave a team with given collective
		 *
		 * @param {object} collective the collective with id and circleId
		 */
		async leaveCircle(collective) {
			const collectivesStore = useCollectivesStore()
			await axios.put(generateOcsUrl(`apps/circles/circles/${collective.circleId}/leave`))
			this.circles.splice(this.circles.findIndex((c) => c.id === collective.circleId), 1)
			collectivesStore.removeCollectiveFromState(collective)
		},

	},
})
