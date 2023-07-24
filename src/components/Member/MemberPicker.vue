<template>
	<div class="member-picker">
		<!-- Search -->
		<NcTextField ref="memberSearch"
			:value.sync="searchQuery"
			type="text"
			:show-trailing-button="isSearching"
			:label="t('collectives', 'Search users, groups, circles…')"
			@trailing-button-click="clearSearch"
			@input="onSearch">
			<MagnifyIcon :size="16" />
		</NcTextField>

		<div class="member-picker-list">
			<!-- Current members (optional) -->
			<SkeletonLoading v-if="showCurrentSkeleton" type="members-list" :count="3" />
			<CurrentMembers v-else-if="showCurrent"
				:circle-id="circleId"
				:current-members="currentMembers"
				:search-query="searchQuery" />

			<!-- Selected members (optional) -->
			<SelectedMembers v-if="!showCurrentSkeleton && showSelection"
				:selected-members="selectedMembers"
				@delete-from-selection="deleteFromSelection" />

			<!-- Searched and picked members -->
			<MemberSearchResults v-if="!showCurrentSkeleton && hasSearchResults"
				:circle-id="circleId"
				:search-results="filteredSearchResults"
				:selection-set="selectedMembers"
				@click="onClickSearched" />

			<!-- No search results -->
			<template v-else-if="!showCurrentSkeleton">
				<NcAppNavigationCaption class="member-picker-caption" :title="t('collectives', 'Add users, groups or circles…')" />
				<Hint v-if="!isSearching" :hint="t('collectives', 'Search for members to add')" />
				<Hint v-else-if="isSearchLoading" :hint="t('collectives', 'Loading…')" />
				<Hint v-else :hint="t('collectives', 'No search results')" />
			</template>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { autocompleteSourcesToCircleMemberTypes, circlesMemberTypes, shareTypes } from '../../constants.js'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { NcAppNavigationCaption, NcTextField } from '@nextcloud/vue'
import CurrentMembers from './CurrentMembers.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import Hint from './Hint.vue'
import MemberSearchResults from './MemberSearchResults.vue'
import SelectedMembers from './SelectedMembers.vue'
import SkeletonLoading from '../SkeletonLoading.vue'

export default {
	name: 'MemberPicker',

	components: {
		NcAppNavigationCaption,
		CurrentMembers,
		Hint,
		MagnifyIcon,
		MemberSearchResults,
		NcTextField,
		SelectedMembers,
		SkeletonLoading,
	},

	props: {
		circleId: {
			type: String,
			default: null,
		},
		showCurrent: {
			type: Boolean,
			default: false,
		},
		showSelection: {
			type: Boolean,
			default: false,
		},
		currentMembers: {
			type: Array,
			default() {
				return []
			},
		},
		selectedMembers: {
			type: Object,
			default() {
				return {}
			},
		},
	},

	data() {
		return {
			searchQuery: '',
			searchResults: [],
			isSearchLoading: false,
		}
	},

	computed: {
		isSearching() {
			return this.searchQuery !== ''
		},

		hasSearchResults() {
			return this.filteredSearchResults.length !== 0
		},

		filteredSearchResults() {
			return this.searchResults.filter(this.filterCurrentMembers)
		},

		showCurrentSkeleton() {
			return this.showCurrent && this.currentMembers.length === 0
		},
	},

	mounted() {
		this.$nextTick(() => {
			this.$refs.memberSearch.$el.getElementsByTagName('input')[0]?.focus()
		})
	},

	methods: {
		clearSearch() {
			this.searchQuery = ''
		},

		debounceFetchSearchResults: debounce(function() {
			if (this.isSearching) {
				this.fetchSearchResults()
			}
		}, 250),

		async fetchSearchResults() {
			// Search for users, groups and circles
			const searchShareTypes = [shareTypes.TYPE_USER, shareTypes.TYPE_GROUP, shareTypes.TYPE_CIRCLE]

			try {
				const response = await axios.get(generateOcsUrl('core/autocomplete/get'), {
					params: {
						format: 'json',
						search: this.searchQuery,
						itemType: 'file',
						shareTypes: searchShareTypes,
					},
				})
				this.isSearchLoading = false

				this.searchResults = response.data.ocs.data
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'An error occurred while performing the search'))
				this.isSearchLoading = false
			}
		},

		// Filter out current members
		filterCurrentMembers(item) {
			return !this.currentMembers.find(m => {
				return m.userType === circlesMemberTypes[autocompleteSourcesToCircleMemberTypes[item.source]]
					&& m.displayName === item.label
			})
		},

		deleteFromSelection(member) {
			this.$emit('delete-from-selection', member)
		},

		onClickSearched(member) {
			this.$emit('click-member', member)
			this.$emit('click-searched', member)
		},

		onSearch() {
			this.searchResults = []
			this.isSearchLoading = true
			this.debounceFetchSearchResults()
		},
	},
}
</script>

<style lang="scss" scoped>
.member-picker {
	position: relative;
	display: flex;
	flex-direction: column;
	gap: 0.5rem;
	height: 100%;

	&-caption:not(:first-child) {
		margin-top: 0;
	}

	&-list {
		height: 100%;
		overflow-y: auto;
	}
}
</style>
