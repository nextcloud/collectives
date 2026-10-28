<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul class="print-page-list">
		<li v-for="node in nodes" :key="node.page.id">
			<NcCheckboxRadioSwitch
				:modelValue="selectedIds.has(node.page.id)"
				@update:modelValue="$emit('toggle', node.page.id, $event)">
				{{ pageTitle(node.page) }}
			</NcCheckboxRadioSwitch>
			<CollectivePrintPageList
				v-if="node.children.length"
				:nodes="node.children"
				:selectedIds
				:collective
				@toggle="(pageId: number, checked: boolean) => $emit('toggle', pageId, checked)" />
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
	name: 'CollectivePrintPageList',

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
		toggle: (pageId: number, checked: boolean) => typeof pageId === 'number' && typeof checked === 'boolean',
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
.print-page-list .print-page-list {
	padding-inline-start: 20px;
}
</style>
