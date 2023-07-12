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
				:key="`member-${member.source}-${member.id}`"
				:margin="0"
				:size="22"
				:display-name="member.label"
				:avatar-image="selectedMemberAvatarImage(member)"
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
			:selection-set="selectionSet"
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
import { NcEmptyContent, NcLoadingIcon, NcTextField, NcUserBubble } from '@nextcloud/vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import MagnifyIcon from 'vue-material-design-icons/Magnify.vue'
import MemberSearchResults from '../Member/MemberSearchResults.vue'

export default {
	name: 'MemberPicker',

	components: {
		CloseIcon,
		MagnifyIcon,
		MemberSearchResults,
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

		hasSearchResults() {
			return this.searchResults.length !== 0
		},

		hasSelectedMembers() {
			return Object.keys(this.selectionSet).length !== 0
		},

		selectedMemberAvatarImage() {
			return function(member) {
				return member.source === 'users' ? null : 'icon-group-white'
			}
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

		addMember(member) {
			this.$set(this.selectionSet, `${member.source}-${member.id}`, member)
			this.$emit('update-selection', this.selectionSet)
		},

		deleteMember(member) {
			this.$delete(this.selectionSet, `${member.source}-${member.id}`, member)
			this.$emit('update-selection', this.selectionSet)
		},

		onClickMember(member) {
			if (`${member.source}-${member.id}` in this.selectionSet) {
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
</style>
