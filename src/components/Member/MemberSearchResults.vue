<template>
	<div class="member-search-results">
		<template v-if="addableUsers.length !== 0">
			<NcAppNavigationCaption :title="t('collectives', 'Add users')"
				class="member-picker-caption" />
			<NcUserBubble v-for="item in addableUsers"
				:key="generateKey(item)"
				class="member-picker-bubble"
				:class="{'member-picker-bubble-selected': isSelected(item)}"
				:display-name="item.label"
				:user="item.user"
				:margin="6"
				:size="44"
				@click.stop.prevent="onClick(item)">
				<template #title>
					<div class="member-picker-bubble-checkmark">
						<CheckIcon :size="16" />
					</div>
				</template>
			</NcUserBubble>
		</template>

		<template v-if="addableGroups.length !== 0">
			<NcAppNavigationCaption :title="t('collectives', 'Add groups')"
				class="member-picker-caption" />
			<NcUserBubble v-for="item in addableGroups"
				:key="generateKey(item)"
				class="member-picker-bubble"
				:class="{'member-picker-bubble-selected': isSelected(item)}"
				:display-name="item.label"
				:user="item.user"
				avatar-image="icon-group-white"
				:margin="6"
				:size="44"
				@click.stop.prevent="onClick(item)">
				<template #title>
					<div class="member-picker-bubble-checkmark">
						<CheckIcon :size="16" />
					</div>
				</template>
			</NcUserBubble>
		</template>

		<template v-if="addableCircles.length !== 0">
			<NcAppNavigationCaption :title="t('collectives', 'Add circles')"
				class="member-picker-caption" />
			<NcUserBubble v-for="item in addableCircles"
				:key="generateKey(item)"
				class="member-picker-bubble"
				:class="{'member-picker-bubble-selected': isSelected(item)}"
				:display-name="item.label"
				:user="item.user"
				avatar-image="icon-group-white"
				:margin="6"
				:size="44"
				@click.stop.prevent="onClick(item)">
				<template #title>
					<div class="member-picker-bubble-checkmark">
						<CheckIcon :size="16" />
					</div>
				</template>
			</NcUserBubble>
		</template>
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { NcAppNavigationCaption, NcUserBubble } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

export default {
	name: 'MemberSearchResults',

	components: {
		CheckIcon,
		NcAppNavigationCaption,
		NcUserBubble,
	},

	props: {
		searchResults: {
			type: Array,
			required: true,
		},
		selectionSet: {
			type: Object,
			required: true,
		},
	},

	computed: {
		addableUsers() {
			if (this.searchResults === []) {
				return []
			}

			const searchResultUsers = this.searchResults.filter(item => item.source === 'users')
			return searchResultUsers.filter(user => {
				if (user.id === getCurrentUser().uid) {
					return false
				}
				return true
			})
		},

		addableGroups() {
			if (this.searchResults === []) {
				return []
			}

			return this.searchResults.filter(item => item.source === 'groups')
		},

		addableCircles() {
			if (this.searchResults === []) {
				return []
			}

			return this.searchResults.filter(item => item.source === 'circles')
		},

		isSelected() {
			return function(item) {
				return `${item.source}-${item.id}` in this.selectionSet
			}
		},
	},

	methods: {
		onClick(entity) {
			this.$emit('click', entity)
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
.member-search-results {
	height: 100%;
	overflow-y: auto;
}

.member-picker {
	&-caption:not(:first-child) {
		margin-top: 0;
	}

	&-bubble {
		// Overwrite .user-bubble__wrapper styling from NcUserBubble
		display: flex !important;
		margin-bottom: 4px;

		:deep(.user-bubble__content) {
			background-color: var(--color-main-background);
			align-items: center;
			width: 100%;
		}

		:deep(.user-bubble__title) {
			width: calc(100% - 80px);
		}

		&-checkmark {
			display: block;
			margin-right: -4px;
			opacity: 0;
		}

		// Show checkmark on selected
		&-selected .member-picker-bubble-checkmark {
			opacity: 1;
		}

		// Show primary bg on hovering entities
		&-selected, &:hover, &:focus {
			:deep(.user-bubble__content) {
				// better visual with light default tint
				background-color: var(--color-primary-element-light);
			}
		}
	}
}
</style>
