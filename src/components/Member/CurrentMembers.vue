<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="current-members">
		<NcAppNavigationCaption
			v-if="isSearching"
			:name="t('collectives', 'Members')" />
		<MemberItem
			v-for="item in searchedMembers"
			:key="item.singleId"
			:circle-id="circleId"
			:current-user-is-admin="currentUserIsAdmin"
			:member-id="item.id"
			:user-id="item.userId"
			:display-name="item.displayName"
			:user-type="circleMemberType(item)"
			:level="item.level"
			:is-current-user="isCurrentUser(item)"
			:is-searched="false" />
		<MembersHint
			v-if="isSearching && searchedMembers.length === 0"
			:hint="t('collectives', 'No search results')" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcAppNavigationCaption } from '@nextcloud/vue'
import { mapState } from 'pinia'
import MemberItem from './MemberItem.vue'
import MembersHint from './MembersHint.vue'
import { useCirclesStore } from '../../stores/circles.js'

export default {
	name: 'CurrentMembers',

	components: {
		MembersHint,
		MemberItem,
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

		currentUserIsAdmin: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		...mapState(useCirclesStore, ['circleMemberType']),

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
				.filter((m) => m.displayName.toLowerCase().includes(this.searchQuery.toLowerCase()))
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
