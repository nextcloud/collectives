<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="publish-tree-row"
		:style="{ '--publish-tree-depth': depth }">
		<NcButton
			v-if="expandable"
			:aria-label="expandButtonLabel"
			variant="tertiary"
			class="publish-tree-row__expand-button"
			@click="$emit('toggleExpand')">
			<template #icon>
				<ChevronDownIcon
					:size="20"
					:class="{ 'publish-tree-row__expand-icon--collapsed': !expanded }" />
			</template>
		</NcButton>
		<span v-else class="publish-tree-row__expand-spacer" />
		<NcCheckboxRadioSwitch
			:modelValue="selected"
			:indeterminate="indeterminate"
			class="publish-tree-row__checkbox"
			@update:modelValue="$emit('update:selected', $event)">
			<span class="publish-tree-row__icon">
				<slot name="icon" />
			</span>
			<slot />
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'

export default {
	name: 'PublishTreeRow',

	components: {
		ChevronDownIcon,
		NcButton,
		NcCheckboxRadioSwitch,
	},

	props: {
		depth: {
			type: Number,
			default: 0,
		},

		expandable: {
			type: Boolean,
			default: false,
		},

		expanded: {
			type: Boolean,
			default: false,
		},

		selected: {
			type: Boolean,
			default: false,
		},

		indeterminate: {
			type: Boolean,
			default: false,
		},
	},

	emits: [
		'toggleExpand',
		'update:selected',
	],

	computed: {
		expandButtonLabel() {
			return this.expanded
				? t('collectives', 'Collapse subpages')
				: t('collectives', 'Expand subpages')
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.publish-tree-row {
	display: flex;
	align-items: center;
	gap: 4px;
	padding-inline-start: calc(var(--publish-tree-depth) * 24px);

	&__expand-button {
		flex: 0 0 auto;
	}

	&__expand-icon--collapsed {
		transform: rotate(-90deg);
	}

	&__expand-spacer {
		display: inline-block;
		width: 44px;
		flex: 0 0 auto;
	}

	&__checkbox {
		display: flex;
		align-items: center;
		min-width: 0;

		:deep(label) {
			display: flex;
			align-items: center;
			gap: 4px;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
	}

	&__icon {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 20px;
		flex: 0 0 auto;
	}
}
</style>
