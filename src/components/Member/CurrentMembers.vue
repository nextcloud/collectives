<template>
	<div class="current-members">
		<NcAppNavigationCaption v-if="isSearching"
			:title="t('collectives', 'Members')" />
		<Member v-for="item in searchedMembers"
			:key="item.singleId"
			:circle-id="circleId"
			:member-id="item.id"
			:user-id="item.userId"
			:display-name="item.displayName"
			:user-type="circleMemberType(item)"
			:level="item.level"
			:is-current-user="isCurrentUser(item)"
			:is-searched="false" />
		<Hint v-if="isSearching && searchedMembers.length === 0"
			:hint="t('collectives', 'No search results')" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
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
		circleId: {
			type: String,
			required: true,
		},
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
		...mapGetters([
			'circleMemberType',
		]),

		isSearching() {
			return this.searchQuery !== ''
		},

		sortedMembers() {
			return this.currentMembers
				.slice()
				.sort(this.sortCurrentUserFirst)
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

		isCurrentUser() {
			return function(item) {
				return item.userId === this.currentUser
					|| item.singleId === item.circle.initiator.singleId
			}
		},
	},

	methods: {
		/**
		 * @param {object} m1 First member
		 * @param {string} m1.userId First member user ID
		 * @param {object} m2 Second member
		 * @param {string} m2.userId Second member user ID
		 */
		sortCurrentUserFirst(m1, m2) {
			if (m1.userId === this.currentUser) {
				return -1
			} else if (m2.userId === this.currentUser) {
				return 1
			}
			return 0
		},
	},
}
</script>
