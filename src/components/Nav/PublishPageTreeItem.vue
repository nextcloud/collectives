<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="publish-page-tree-item">
		<PublishTreeRow
			:depth="depth"
			:expandable="hasSubpages && !filteredPageIds"
			:expanded="isExpanded"
			:selected="isSelected"
			:indeterminate="isIndeterminate"
			@toggleExpand="$emit('toggleExpand', page.id)"
			@update:selected="$emit('toggleSelect', page.id)">
			<template #icon>
				<span v-if="page.emoji">{{ page.emoji }}</span>
				<PageIcon v-else :size="20" fillColor="var(--color-text-maxcontrast)" />
			</template>
			<NcHighlight :text="page.title" :search="filter" />
			<template v-if="descendantIds.length" #details>
				{{ n('collectives', '{selected}/%n subpage', '{selected}/%n subpages', descendantIds.length, { selected: selectedDescendantCount }) }}
			</template>
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
				:filter="filter"
				:filteredPageIds="filteredPageIds"
				@toggleSelect="$emit('toggleSelect', $event)"
				@toggleExpand="$emit('toggleExpand', $event)" />
		</ul>
	</li>
</template>

<script>
import { n } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import NcHighlight from '@nextcloud/vue/components/NcHighlight'
import PageIcon from '../Icon/PageIcon.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'PublishPageTreeItem',

	components: {
		NcHighlight,
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

		/** Text highlighted in the page title */
		filter: {
			type: String,
			default: '',
		},

		/** Pages shown while filtering, null if not filtering */
		filteredPageIds: {
			type: Set,
			default: null,
		},
	},

	emits: [
		'toggleSelect',
		'toggleExpand',
	],

	computed: {
		...mapState(usePagesStore, [
			'pagesTreeWalkForCollective',
			'visibleSubpagesForCollective',
		]),

		subpages() {
			const subpages = this.visibleSubpagesForCollective(this.collective, this.page.id)
			return this.filteredPageIds
				? subpages.filter((subpage) => this.filteredPageIds.has(subpage.id))
				: subpages
		},

		hasSubpages() {
			return this.subpages.length > 0
		},

		isSelected() {
			return this.selectedPageIds.has(this.page.id)
		},

		// All subpages at any depth, also the ones hidden by a filter
		descendantIds() {
			return this.pagesTreeWalkForCollective(this.collective, this.page.id).map((subpage) => subpage.id)
		},

		selectedDescendantCount() {
			return this.descendantIds.filter((id) => this.selectedPageIds.has(id)).length
		},

		// Marks partially selected subtrees, especially useful while they're collapsed
		isIndeterminate() {
			return this.isSelected && this.selectedDescendantCount < this.descendantIds.length
		},

		// While filtering, all matches are shown regardless of the expanded state
		isExpanded() {
			return !!this.filteredPageIds || this.expandedPageIds.has(this.page.id)
		},
	},

	methods: {
		n,
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
