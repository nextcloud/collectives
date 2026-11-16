<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul class="publish-page-selection">
		<li v-if="rootPage" class="publish-page-selection__collective-row">
			<PublishTreeRow
				:selected="isRootPageSelected"
				:indeterminate="isRootPageIndeterminate"
				@update:selected="onToggleSelect(rootPage.id)">
				<template #icon>
					<span v-if="collective.emoji">{{ collective.emoji }}</span>
					<PageTemplateIcon v-else :size="20" fillColor="var(--color-text-maxcontrast)" />
				</template>
				<strong>{{ collective.name }}</strong>
			</PublishTreeRow>
		</li>
		<PublishPageTreeItem
			v-for="page in topLevelPages"
			:key="page.id"
			:collective="collective"
			:page="page"
			:depth="1"
			:selectedPageIds="selectedPageIds"
			:expandedPageIds="expandedPageIds"
			@toggleSelect="onToggleSelect"
			@toggleExpand="onToggleExpand" />
	</ul>
</template>

<script>
import { mapState } from 'pinia'
import PageTemplateIcon from '../Icon/PageTemplateIcon.vue'
import PublishPageTreeItem from './PublishPageTreeItem.vue'
import PublishTreeRow from './PublishTreeRow.vue'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'PublishPageSelection',

	components: {
		PageTemplateIcon,
		PublishPageTreeItem,
		PublishTreeRow,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		/** @type {Set<number>} */
		selectedPageIds: {
			type: Set,
			required: true,
		},
	},

	emits: [
		'update:selectedPageIds',
	],

	data() {
		return {
			expandedPageIds: new Set(),
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'pageAncestorIdsForCollective',
			'pagesForCollective',
			'pagesTreeWalkForCollective',
			'visibleSubpagesForCollective',
		]),

		allPageIds() {
			return this.pagesTreeWalkForCollective(this.collective).map((page) => page.id)
		},

		rootPage() {
			return this.pagesForCollective(this.collective).find((page) => page.parentId === 0)
		},

		topLevelPages() {
			return this.rootPage
				? this.visibleSubpagesForCollective(this.collective, this.rootPage.id)
				: []
		},

		isRootPageSelected() {
			return !!this.rootPage && this.selectedPageIds.has(this.rootPage.id)
		},

		// Marks a partially selected collective, especially useful while its pages are collapsed
		isRootPageIndeterminate() {
			return this.isRootPageSelected
				&& this.allPageIds.some((id) => !this.selectedPageIds.has(id))
		},
	},

	methods: {
		onToggleSelect(pageId) {
			// Toggling a page also selects/deselects all of its subpages
			const select = !this.selectedPageIds.has(pageId)
			const affectedIds = [
				pageId,
				...this.pagesTreeWalkForCollective(this.collective, pageId).map((page) => page.id),
			]

			const selectedPageIds = new Set(this.selectedPageIds)
			for (const id of affectedIds) {
				if (select) {
					selectedPageIds.add(id)
				} else {
					selectedPageIds.delete(id)
				}
			}

			// Selecting a page also selects its ancestor pages, so a subpage is never
			// published without the pages it is nested in
			if (select) {
				for (const ancestorId of this.pageAncestorIdsForCollective(this.collective, pageId)) {
					selectedPageIds.add(ancestorId)
				}
			}

			this.$emit('update:selectedPageIds', selectedPageIds)
		},

		onToggleExpand(pageId) {
			const expandedPageIds = new Set(this.expandedPageIds)
			if (expandedPageIds.has(pageId)) {
				expandedPageIds.delete(pageId)
			} else {
				expandedPageIds.add(pageId)
			}
			this.expandedPageIds = expandedPageIds
		},
	},
}
</script>

<style lang="scss" scoped>
.publish-page-selection {
	flex: 1 1 auto;
	margin: 0;
	padding: 0 0 8px;
	overflow-y: auto;

	&__collective-row {
		list-style: none;
	}
}
</style>
