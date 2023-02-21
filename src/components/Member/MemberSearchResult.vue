<template>
	<NcAppNavigationCaption v-if="entity.heading"
		:key="entity.id"
		:title="t('collectives', 'Add {type}', {type: entity.label.toLowerCase()})"
		class="member-picker-caption" />

	<NcUserBubble v-else
		class="member-picker-bubble"
		:class="{'member-picker-bubble-selected': isSelected}"
		:display-name="entity.label"
		:user="entity.user"
		:margin="6"
		:size="44"
		@click.stop.prevent="onClick(entity)">
		<template #title>
			<div class="member-picker-bubble-checkmark">
				<CheckIcon :size="16" />
			</div>
		</template>
	</NcUserBubble>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcAppNavigationCaption, NcUserBubble } from '@nextcloud/vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'

export default {
	name: 'MemberSearchResult',

	components: {
		CheckIcon,
		NcAppNavigationCaption,
		NcUserBubble,
	},

	props: {
		entity: {
			type: Object,
			default() {
				return {}
			},
		},
		isSelected: {
			type: Boolean,
			default: false,
		},
	},

	methods: {
		t,

		onClick(entity) {
			this.$emit('click', entity)
		},
	},
}
</script>

<style lang="scss" scoped>
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
				background-color: var(--color-primary-light);
			}
		}
	}
}
</style>
