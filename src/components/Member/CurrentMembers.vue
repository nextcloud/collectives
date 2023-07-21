<template>
	<div class="current-members">
		<NcAppNavigationCaption v-if="isSearching"
			:title="t('collectives', 'Members')" />
		<Member v-for="item in searchedMembers"
			:key="item.singleId"
			:display-name="item.displayName"
			:user-id="item.userId"
			:user-type="item.userType"
			:level="item.level"
			:is-current-user="item.userId === currentUser"
			:is-searched="false" />
		<Hint v-if="isSearching && searchedMembers.length === 0"
			:hint="t('collectives', 'No search results')" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcAppNavigationCaption } from '@nextcloud/vue'
import Hint from './Hint.vue'
import Member from './Member.vue'

export default {
	name: 'CurrentMembers',

	components: {
		Hint,
		Member,
		NcAppNavigationCaption,
	},

	props: {
		currentMembers: {
			type: Array,
			required: true,
		},
		searchQuery: {
			type: String,
			default: '',
		},
	},

	computed: {
		isSearching() {
			return this.searchQuery !== ''
		},

		sortedMembers() {
			return this.currentMembers
				.slice()
				.sort(this.sortMembers)
		},

		searchedMembers() {
			if (!this.isSearching) {
				return this.sortedMembers
			}

			return this.sortedMembers
				.filter(m => m.displayName.toLowerCase().includes(this.searchQuery.toLowerCase()))
		},

		currentUser() {
			return getCurrentUser().uid
		},
	},

	methods: {
		/**
		 *
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
		sortMembers(m1, m2) {
			if (m1.userId === this.currentUser) {
				return -1
			} else if (m2.userId === this.currentUser) {
				return 1
			}

			// Sort by level (admin > moderator > member)
			if (m1.level !== m2.level) {
				return m1.level < m2.level
			}

			// Sort by user type (user > group > circle)
			if (m1.userType !== m2.userType) {
				return m1.userType > m2.userType
			}

			// Sort by display name
			return m1.displayName.localeCompare(m2.displayName)
		},
	},
}
</script>
