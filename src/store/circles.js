import { set } from 'vue'
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'
import {
	GET_CIRCLES,
	RENAME_CIRCLE,
	GET_CIRCLE_MEMBERS,
	ADD_MEMBER_TO_CIRCLE,
	ADD_MEMBERS_TO_CIRCLE,
	CHANGE_CIRCLE_MEMBER_LEVEL,
	REMOVE_MEMBER_FROM_CIRCLE,
	LEAVE_CIRCLE,
	GET_PAGES,
	GET_TRASH_PAGES,
} from './actions.js'
import {
	SET_CIRCLES,
	UPDATE_CIRCLE,
	DELETE_CIRCLE_FOR,
	SET_CIRCLE_MEMBERS,
	PATCH_COLLECTIVE_WITH_CIRCLE,
	REMOVE_COLLECTIVE,
} from './mutations.js'
import { circlesMemberTypes } from '../constants.js'

export default {
	state: {
		circles: [],
		circleMembers: {},
	},

	getters: {
		availableCircles(state, _getters, rootState) {
			return state.circles
				.filter(circle => circle.initiator) // only circles i am a member of
				.filter(circle => {
					const matchCircleId = c => {
						return (c.circleId === circle.id)
					}
					const alive = rootState.collectives.collectives.find(matchCircleId)
					const trashed = rootState.collectives.trashCollectives.find(matchCircleId)
					return !alive && !trashed
				})
		},

		circleMembers: (state) => (circleId) => state.circleMembers[circleId] || [],

		circleMemberType: () => (member) => {
			// If the user type is a circle, this could originate from multiple sources
			// Copied from Contacts app src/models/member.ts get userType()
			return member.userType !== circlesMemberTypes.TYPE_CIRCLE
				? member.userType
				: member.basedOn.source
		},

		circleMembersSorted: (state, getters) => (circleId) => {
			/**
			 * @param {object} m1 First member
			 * @param {string} m1.userId First member user ID
			 * @param {string} m1.displayName First member display name
			 * @param {number} m1.level First member level
			 * @param {number} m1.userType First member user type
			 * @param {object} m2 Second member
			 * @param {string} m2.userId Second member user ID
			 * @param {string} m2.displayName Second member display name
			 * @param {number} m2.level Second member level
			 * @param {number} m2.userType Second member user type
			 */
			function sortMembersByLevelAndType(m1, m2) {
				// Sort by level (admin > moderator > member)
				if (m1.level !== m2.level) {
					return m1.level < m2.level
				}

				// Sort by user type (user > group > circle)
				if (getters.circleMemberType(m1) !== getters.circleMemberType(m2)) {
					return getters.circleMemberType(m1) > getters.circleMemberType(m2)
				}

				// Sort by display name
				return m1.displayName.localeCompare(m2.displayName)
			}

			return getters.circleMembers(circleId)
				.slice()
				.sort(sortMembersByLevelAndType)
		},
	},

	mutations: {
		[SET_CIRCLES](state, circles) {
			state.circles = circles
		},

		[DELETE_CIRCLE_FOR](state, collective) {
			state.circles.splice(state.circles.findIndex(c => c.id === collective.circleId), 1)
		},

		[UPDATE_CIRCLE](state, circle) {
			state.circles.splice(
				state.circles.findIndex(c => c.id === circle.id),
				1,
				circle,
			)
		},

		[SET_CIRCLE_MEMBERS](state, { circleId, members }) {
			set(state.circleMembers, circleId, members)
		},
	},

	actions: {
		/**
		 * Get list of all circles
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 */
		async [GET_CIRCLES]({ commit, getters }) {
			if (getters.isPublic) {
				return
			}
			const response = await axios.get(generateOcsUrl('apps/circles/circles'))
			commit(SET_CIRCLES, response.data.ocs.data)
		},

		/**
		 * Rename a circle
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} store.getters getters of the store
		 * @param {Function} store.dispatch dispatch actions
		 * @param {object} collective the collective with circleId and name
		 */
		async [RENAME_CIRCLE]({ commit, getters, dispatch }, collective) {
			const response = await axios.put(
				generateOcsUrl('apps/circles/circles/' + collective.circleId + '/name'),
				{ value: collective.name },
			)
			commit(UPDATE_CIRCLE, response.data.ocs.data)

			if (collective.id === getters.currentCollective?.id) {
				// Update page list, properties like `collectivePath` might have changed
				await dispatch(GET_PAGES, false)
				await dispatch(GET_TRASH_PAGES)
			}
			commit(PATCH_COLLECTIVE_WITH_CIRCLE, response.data.ocs.data)
		},

		/**
		 * Get members of a circle
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {string} circleId ID of the circle
		 */
		async [GET_CIRCLE_MEMBERS]({ commit }, circleId) {
			const response = await axios.get(generateOcsUrl(`apps/circles/circles/${circleId}/members`))
			commit(SET_CIRCLE_MEMBERS, { circleId, members: response.data.ocs.data })
		},

		/**
		 * Add a single member to a circle
		 *
		 * @param {object} _ the vuex store
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the circle
		 * @param {string} params.userId User ID of the member to be added
		 * @param {number} params.type Type of the member to be added
		 */
		async [ADD_MEMBER_TO_CIRCLE](_, { circleId, userId, type }) {
			const response = await axios.post(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members'),
				{ userId, type },
			)
			console.debug('Added member to circle', circleId, response.data.ocs.data)
			return response.data.ocs.data
		},

		/**
		 * Add multiple members to a circle
		 *
		 * @param {object} _ the vuex store
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the circle
		 * @param {object} params.members Object with members to be added
		 */
		async [ADD_MEMBERS_TO_CIRCLE](_, { circleId, members }) {
			const response = await axios.post(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members/multi'),
				{ members },
			)
			console.debug('Added members to circle', circleId, response.data.ocs.data)
			return response.data.ocs.data
		},

		/**
		 * Remove a single member to a circle
		 *
		 * @param {object} _ the vuex store
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the circle
		 * @param {string} params.memberId Circle member ID of the member to be removed
		 */
		async [REMOVE_MEMBER_FROM_CIRCLE](_, { circleId, memberId }) {
			const response = await axios.delete(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members/' + memberId),
			)
			console.debug('Removed member from circle', circleId, response.data.ocs.data)
			return response.data.ocs.data
		},

		/**
		 * Change a member level in a circle
		 *
		 * @param {object} _ the vuex store
		 * @param {object} params the params object
		 * @param {string} params.circleId ID of the circle
		 * @param {string} params.memberId Circle member ID of the member to be changed
		 * @param {number} params.level Level of the member to be changed
		 */
		async [CHANGE_CIRCLE_MEMBER_LEVEL](_, { circleId, memberId, level }) {
			const response = await axios.put(
				generateOcsUrl('apps/circles/circles/' + circleId + '/members/' + memberId + '/level'),
				{ level },
			)
			console.debug('Changed level of member from circle', circleId, response.data.ocs.data)
			return response.data.ocs.data
		},

		/**
		 * Leave a circle with given collective
		 *
		 * @param {object} store the vuex store
		 * @param {Function} store.commit commit changes
		 * @param {object} collective the collective with id and circleId
		 */
		async [LEAVE_CIRCLE]({ commit }, collective) {
			await axios.put(generateOcsUrl(`apps/circles/circles/${collective.circleId}/leave`))
			commit(DELETE_CIRCLE_FOR, collective)
			commit(REMOVE_COLLECTIVE, collective)
		},
	},
}
