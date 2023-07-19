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

		<!-- Selected members -->
		<SelectedMembers v-if="showSelection"
			:selected-members="selectedMembers"
			@delete-from-selection="deleteFromSelection" />

		<!-- No search yet -->
		<NcEmptyContent v-if="!isSearching && !hasSearchResults"
			:title="t('collectives', 'Search for people to add')"
			class="empty-content">
			<template #icon>
				<MagnifyIcon :size="20" />
			</template>
		</NcEmptyContent>

		<!-- Loading -->
		<NcEmptyContent v-else-if="membersLoading" :title="t('collectives', 'Loading …')">
			<template #icon>
				<NcLoadingIcon :size="20" />
			</template>
		</NcEmptyContent>

		<!-- Searched and picked members -->
		<MemberSearchResults v-else-if="hasSearchResults"
			:search-results="searchResults"
			:selection-set="selectedMembers"
			@click="onClickMember" />

		<!-- No results -->
		<NcEmptyContent v-else
			:title="t('collectives', 'No search results')"
			class="empty-content">
			<template #icon>
				<MagnifyIcon :size="20" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import debounce from 'debounce'
import { shareTypes } from '../../constants.js'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { NcEmptyContent, NcLoadingIcon, NcTextField } from '@nextcloud/vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import MemberSearchResults from './MemberSearchResults.vue'
import SelectedMembers from './SelectedMembers.vue'

export default {
	name: 'MemberPicker',

	components: {
		MagnifyIcon,
		MemberSearchResults,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		SelectedMembers,
	},

	props: {
		showSelection: {
			type: Boolean,
			default: false,
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
			membersLoading: false,
		}
	},

	computed: {
		isSearching() {
			return this.searchQuery !== ''
		},

		hasSearchResults() {
			return this.searchResults.length !== 0
		},
	},

	beforeMount() {
		this.fetchSearchResults()
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
				this.membersLoading = false
				this.searchResults = response.data.ocs.data
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'An error occurred while performing the search'))
				this.membersLoading = false
			}
		},

		deleteFromSelection(member) {
			this.$emit('delete-from-selection', member)
		},

		onClickMember(member) {
			this.$emit('click-member', member)
		},

		onSearch() {
			this.searchResults = []
			this.membersLoading = true
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
	// TODO: Fix cropped bottom
	height: 100%;
}
</style>
