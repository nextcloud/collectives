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
				@update:modelValue="$emit('toggle', node, $event)">
				{{ pageTitle(node.page) }}
			</NcCheckboxRadioSwitch>
			<CollectivePrintPageTree
				v-if="node.children.length"
				:nodes="node.children"
				:selectedIds
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

export default defineComponent({
	name: 'CollectivePrintPageTree',

	components: {
		NcCheckboxRadioSwitch,
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
	},

	emits: {
		toggle: (node: PageTreeNode<PageInfo>, checked: boolean) => typeof node === 'object' && typeof checked === 'boolean',
	},

	methods: {
		pageTitle(page: PageInfo): string {
			const isCollectivePage = page.parentId === 0
			const title = isCollectivePage ? this.collective.name : page.title
			const emoji = isCollectivePage ? this.collective.emoji : page.emoji
			return emoji ? `${emoji} ${title}` : title
		},
	},
})
</script>

<style scoped lang="scss">
.print-page-tree .print-page-tree {
	padding-inline-start: 20px;
}

.print-page-tree__checkbox {
	min-width: 0;

	:deep(.checkbox-content__text) {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
}
</style>
