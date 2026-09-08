<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="publish-page-tree-item">
		<PublishTreeRow
			:depth="depth"
			:expandable="hasSubpages"
			:expanded="isExpanded"
			:selected="isSelected"
			@toggleExpand="$emit('toggleExpand', page.id)"
			@update:selected="$emit('toggleSelect', page.id)">
			<template #icon>
				<span v-if="page.emoji">{{ page.emoji }}</span>
				<PageIcon v-else :size="20" fillColor="var(--color-text-maxcontrast)" />
			</template>
			{{ page.title }}
		</PublishTreeRow>
		<ul v-if="hasSubpages && isExpanded" class="publish-page-tree-item__children">
			<PublishPageTreeItem
				v-for="subpage in subpages"
				:key="subpage.id"
				:collective="collective"
				:page="subpage"
				:depth="depth + 1"
				:selectedPageIds="selectedPageIds"
				:expandedPageIds="expandedPageIds"
				@toggleSelect="$emit('toggleSelect', $event)"
				@toggleExpand="$emit('toggleExpand', $event)" />
		</ul>
	</li>
</template>

<script>
import { mapState } from 'pinia'
import PageIcon from '../Icon/PageIcon.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'PublishPageTreeItem',

	components: {
		PageIcon,
		PublishTreeRow,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		page: {
			type: Object,
			required: true,
		},

		depth: {
			type: Number,
			default: 0,
		},

		selectedPageIds: {
			type: Set,
			required: true,
		},

		expandedPageIds: {
			type: Set,
			required: true,
		},
	},

	emits: [
		'toggleSelect',
		'toggleExpand',
	],

	computed: {
		...mapState(usePagesStore, ['visibleSubpagesForCollective']),

		subpages() {
			return this.visibleSubpagesForCollective(this.collective, this.page.id)
		},

		hasSubpages() {
			return this.subpages.length > 0
		},

		isSelected() {
			return this.selectedPageIds.has(this.page.id)
		},

		isExpanded() {
			return this.expandedPageIds.has(this.page.id)
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-page-tree-item {
	list-style: none;

	&__children {
		margin: 0;
		padding: 0;
	}
}
</style>
