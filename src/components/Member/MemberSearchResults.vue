<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="member-search-results">
		<template v-if="addableUsers.length !== 0">
			<NcAppNavigationCaption
				:name="t('collectives', 'Add accounts')"
				class="member-picker-caption" />
			<MemberItem
				v-for="item in addableUsers"
				:key="generateKey(item)"
				:circle-id="circleId"
				:current-user-is-admin="true"
				:user-id="item.id"
				:display-name="item.label"
				:user-type="circleUserType(item.source)"
				:is-searched="true"
				:is-selected="isSelected(item)"
				:is-loading="isLoading(item)"
				@click="onClick(item)" />
		</template>

		<template v-if="addableGroups.length !== 0">
			<NcAppNavigationCaption
				:name="t('collectives', 'Add groups')"
				class="member-picker-caption" />
			<MemberItem
				v-for="item in addableGroups"
				:key="generateKey(item)"
				:circle-id="circleId"
				:current-user-is-admin="true"
				:user-id="item.id"
				:display-name="item.label"
				:user-type="circleUserType(item.source)"
				:is-searched="true"
				:is-selected="isSelected(item)"
				:is-loading="isLoading(item)"
				@click="onClick(item)" />
		</template>

		<template v-if="addableCircles.length !== 0">
			<NcAppNavigationCaption
				:name="t('collectives', 'Add teams')"
				class="member-picker-caption" />
			<MemberItem
				v-for="item in addableCircles"
				:key="generateKey(item)"
				:circle-id="circleId"
				:current-user-is-admin="true"
				:user-id="item.id"
				:display-name="item.label"
				:user-type="circleUserType(item.source)"
				:is-searched="true"
				:is-selected="isSelected(item)"
				:is-loading="isLoading(item)"
				@click="onClick(item)" />
		</template>
	</div>
</template>

<script>
import { NcAppNavigationCaption } from '@nextcloud/vue'
import MemberItem from './MemberItem.vue'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes } from '../../constants.js'

export default {
	name: 'MemberSearchResults',

	components: {
		MemberItem,
		NcAppNavigationCaption,
	},

	props: {
		circleId: {
			type: String,
			default: null,
		},

		searchResults: {
			type: Array,
			required: true,
		},

		selectionSet: {
			type: Object,
			required: true,
		},

		onClickSearched: {
			type: Function,
			default() {
				return () => {}
			},
		},
	},

	data() {
		return {
			loadingItems: {},
		}
	},

	computed: {
		addableUsers() {
			return this.searchResults.filter((item) => item.source === 'users')
		},

		addableGroups() {
			return this.searchResults.filter((item) => item.source === 'groups')
		},

		addableCircles() {
			return this.searchResults.filter((item) => item.source === 'circles')
		},

		circleUserType() {
			return function(source) {
				return circlesMemberTypes[autocompleteSourcesToCircleMemberTypes[source]]
			}
		},

		isSelected() {
			return function(item) {
				return `${item.source}-${item.id}` in this.selectionSet
			}
		},

		isLoading() {
			return function(item) {
				return `${item.source}-${item.id}` in this.loadingItems
			}
		},
	},

	methods: {
		async onClick(item) {
			this.$set(this.loadingItems, `${item.source}-${item.id}`, true)
			await this.onClickSearched(item)
			this.$delete(this.loadingItems, `${item.source}-${item.id}`)
		},

		generateKey(item) {
			let key = ''
			if (item.source) {
				// Search result candidate
				key = 'search#' + item.source + '#' + item.id
			} else {
				key = 'search#' + item.id
			}
			return key
		},
	},
}
</script>

<style lang="scss" scoped>
.member-picker {
	&-caption:not(:first-child) {
		margin-top: 0;
	}
}
</style>
