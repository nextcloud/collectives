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
		<transition-group v-if="hasSelectedMembers"
			name="zoom"
			tag="div"
			class="selected-members">
			<NcUserBubble v-for="member in selectionSet"
				:key="member.key || `member-${member.type}-${member.id}`"
				:margin="0"
				:size="22"
				:display-name="member.label"
				class="selected-member-bubble">
				<template #title>
					<a href="#"
						:title="t('collectives', 'Remove {name}', { name: member.label })"
						class="selected-member-bubble-delete"
						@click="deleteMember(member)">
						<CloseIcon :size="16" />
					</a>
				</template>
			</NcUserBubble>
		</transition-group>

		<!-- No search yet -->
		<NcEmptyContent v-if="!searchQuery"
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
		<div v-else-if="availableEntities.length > 0"
			class="search-results">
			<MemberSearchResult v-for="entity in availableEntities"
				:key="entity.id"
				:entity="entity"
				:is-selected="entity.id in selectionSet"
				@click="onClickMember" />
		</div>

		<!-- No results -->
		<NcEmptyContent v-else
			:title="t('collectives', 'No results')"
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
import { pickerTypeGrouping, shareTypes } from '../../constants.js'
import { generateOcsUrl } from '@nextcloud/router'
import { showError } from '@nextcloud/dialogs'
import { NcEmptyContent, NcLoadingIcon, NcTextField, NcUserBubble } from '@nextcloud/vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import MemberSearchResult from '../Member/MemberSearchResult.vue'

export default {
	name: 'MemberPicker',

	components: {
		CloseIcon,
		MagnifyIcon,
		MemberSearchResult,
		NcEmptyContent,
		NcLoadingIcon,
		NcTextField,
		NcUserBubble,
	},

	props: {
		selectionSet: {
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

		hasSelectedMembers() {
			return Object.keys(this.selectionSet).length !== 0
		},

		/**
		 * Returns available entities grouped by types
		 */
		availableEntities() {
			return pickerTypeGrouping.map(type => {
				const dataSet = this.searchResults.filter(entity => entity.typeId === type.id)
				const dataList = [
					{
						id: type.id,
						label: type.label,
						heading: true,
					},
					...dataSet,
				]

				// If no results, hide the type
				if (dataSet.length === 0) {
					return []
				}

				return dataList
			}).flat()
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
			const shareType = [shareTypes.TYPE_USER, shareTypes.TYPE_GROUP, shareTypes.TYPE_CIRCLE]
			const maxAutocompleteResults = parseInt(OC.config['sharing.maxAutocompleteResults'], 10) || 25

			let response = null
			try {
				response = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/sharees'), {
					params: {
						format: 'json',
						itemType: 'file',
						search: this.searchQuery,
						perPage: maxAutocompleteResults,
						shareType,
						lookup: false,
					},
				})
				this.membersLoading = false
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'An error occurred while performing the search'))
				this.membersLoading = false
				return
			}

			const { exact, ...data } = response.data.ocs.data

			// flatten array of arrays
			const rawExactSuggestions = Object.values(exact).reduce((arr, elem) => arr.concat(elem), [])
			const rawSuggestions = Object.values(data).reduce((arr, elem) => arr.concat(elem), [])

			// remove invalid data and format to user-select layout
			const exactSuggestions = this.filterAndSortResults(rawExactSuggestions)
			const suggestions = this.filterAndSortResults(rawSuggestions)
			const allSuggestions = exactSuggestions.concat(suggestions)

			this.searchResults = allSuggestions
		},

		filterAndSortResults(results) {
			return results
				.filter(result => typeof result === 'object')
				.map(share => this.formatResults(share))
				// sort by type so we can get user&groups first...
				.sort((a, b) => a.shareType - b.shareType)
		},

		formatResults(result) {
			const type = pickerTypeGrouping.find(t => t.share === result.value.shareType).type
			const typeId = `picker-${result.value.shareType}`
			return {
				label: result.label,
				id: `${type}-${result.value.shareWith}`,
				// If this is a user, set as user for avatar display by NcUserBubble
				user: [OC.Share.SHARE_TYPE_USER, OC.Share.SHARE_TYPE_REMOTE].indexOf(result.value.shareType) > -1
					? result.value.shareWith
					: null,
				type,
				typeId,
				...result.value,
			}
		},

		addMember(member) {
			this.$set(this.selectionSet, member.id, member)
			this.$emit('update-selection', this.selectionSet)
		},

		deleteMember(member) {
			this.$delete(this.selectionSet, member.id, member)
			this.$emit('update-selection', this.selectionSet)
		},

		onClickMember(member) {
			if (member.id in this.selectionSet) {
				this.deleteMember(member)
				return
			}
			this.addMember(member)
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

.selected-members {
	display: flex;
	flex-wrap: wrap;
	border-bottom: 1px solid var(--color-background-darker);
	padding: 4px 0;
	max-height: 97px;
	overflow-y: auto;
	flex: 1 0 auto;
	align-content: flex-start;

	.selected-member-bubble {
		max-width: calc(50% - 4px);
		margin-right: 4px;
		margin-bottom: 4px;

		:deep(.user-bubble__content) {
			align-items: center;
		}

		&-delete {
			display: block;
			margin-right: -4px;
			opacity: .7;

			&:hover, &active, &focus {
				opacity: 1;
			}
		}
	}
}

.zoom-enter-active {
	animation: zoom-in var(--animation-quick);
}

.zoom-leave-active {
	animation: zoom-in var(--animation-quick) reverse;
	will-change: transform;
}

@keyframes zoom-in {
	0% {
		transform: scale(0);
	}
	100% {
		transform: scale(1);
	}
}

.search-results {
	height: 100%;
	overflow-y: auto;
}

.empty-content {
	height: 100%;
}
</style>
