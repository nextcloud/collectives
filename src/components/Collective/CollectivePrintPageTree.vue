<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul class="print-page-tree">
		<li v-for="node in nodes" :key="node.page.id">
			<NcCheckboxRadioSwitch
				class="print-page-tree__checkbox"
				:modelValue="selectedIds.has(node.page.id)"
				:disabled
				@update:modelValue="$emit('toggle', node, $event)">
				<span class="print-page-tree__label">
					<span class="print-page-tree__icon">
						<template v-if="pageEmoji(node.page)">{{ pageEmoji(node.page) }}</template>
						<PageIcon v-else :size="22" fillColor="var(--color-background-maxcontrast)" />
					</span>
					<span class="print-page-tree__title">{{ pageTitle(node.page) }}</span>
				</span>
			</NcCheckboxRadioSwitch>
			<CollectivePrintPageTree
				v-if="node.children.length"
				:nodes="node.children"
				:selectedIds
				:disabled
				:collective
				@toggle="(child: PageTreeNode<PageInfo>, checked: boolean) => $emit('toggle', child, checked)" />
		</li>
	</ul>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { Collective, PageInfo } from '../../types.ts'
import type { PageTreeNode } from '../../util/pageTree.ts'

import { defineComponent } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import PageIcon from '../Icon/PageIcon.vue'

export default defineComponent({
	name: 'CollectivePrintPageTree',

	components: {
		NcCheckboxRadioSwitch,
		PageIcon,
	},

	props: {
		nodes: {
			type: Array as PropType<PageTreeNode<PageInfo>[]>,
			required: true,
		},

		selectedIds: {
			type: Set as PropType<Set<number>>,
			required: true,
		},

		collective: {
			type: Object as PropType<Collective>,
			required: true,
		},

		disabled: {
			type: Boolean,
			default: false,
		},
	},

	emits: {
		toggle: (node: PageTreeNode<PageInfo>, checked: boolean) => typeof node === 'object' && typeof checked === 'boolean',
	},

	methods: {
		pageTitle(page: PageInfo): string {
			return page.parentId === 0 ? this.collective.name : page.title
		},

		pageEmoji(page: PageInfo): string | undefined {
			return (page.parentId === 0 ? this.collective.emoji : page.emoji) || undefined
		},
	},
})
</script>

<style scoped lang="scss">
.print-page-tree {
	display: flex;
	flex-direction: column;
	padding: 0 var(--default-grid-baseline);

	.print-page-tree {
		padding: 0;
	}

	.print-page-tree .print-page-tree {
		padding-inline-start: 10px;
	}
}

.print-page-tree__checkbox {
	min-width: 0;

	:deep(.checkbox-radio-switch__content) {
		width: 100%;
		max-width: 100%;
		min-width: 0;
		padding-block: 0;
		padding-inline-start: calc((var(--default-clickable-area) - 22px) / 2);
	}

	&:focus-within :deep(.checkbox-radio-switch__content),
	:deep(.checkbox-radio-switch__content:hover) {
		background-color: color-mix(in srgb, var(--color-primary-element) 8%, transparent) !important;
	}

	:deep(.checkbox-content__wrapper),
	:deep(.checkbox-content__text) {
		flex-shrink: 1;
		min-width: 0;
	}

	.print-page-tree__label {
		display: flex;
		align-items: center;
		min-width: 0;
	}

	.print-page-tree__icon {
		display: flex;
		flex: 0 0 auto;
		align-items: center;
		justify-content: center;
		min-width: 22px;
		height: var(--default-clickable-area);
		margin-inline-end: calc((var(--default-clickable-area) - 22px) / 2);
	}

	.print-page-tree__title {
		flex-shrink: 1;
		min-width: 0;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
