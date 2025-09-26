<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<Item
			:key="page.title"
			:to="pagePath(page)"
			:page-id="page.id"
			:parent-id="page.parentId"
			:title="page.title"
			:timestamp="page.timestamp"
			:last-user-id="page.lastUserId"
			:last-user-display-name="page.lastUserDisplayName"
			:emoji="page.emoji"
			:level="level"
			:can-edit="currentCollectiveCanEdit"
			:has-visible-subpages="hasVisibleSubpages"
			:filtered-view="filteredView"
			@click.native="show('details')" />
		<div class="page-list-indent">
			<Draggable
				v-if="subpagesView.length > 0 || keptSortable(page.id)"
				:list="subpagesView"
				:parent-id="page.id"
				:disable-sorting="disableSorting">
				<SubpageList
					v-for="subpage in subpagesView"
					:key="subpage.id"
					:data-page-id="subpage.id"
					:page="subpage"
					:level="level + 1"
					class="page-list-drag-item" />
			</Draggable>
		</div>
	</div>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import Draggable from './Draggable.vue'
import Item from './Item.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'SubpageList',

	components: {
		Draggable,
		Item,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},

		level: {
			type: Number,
			required: true,
		},

		filteredView: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		...mapState(useRootStore, ['pageParam', 'pageId']),
		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),
		...mapState(usePagesStore, [
			'pagePath',
			'currentPageIds',
			'keptSortable',
			'visibleSubpages',
			'isCollapsed',
		]),

		showSubpages() {
			// Display subpages only when not in filtered view and when not collapsed
			return !this.filteredView && !this.isCollapsed(this.page.id)
		},

		subpagesView() {
			if (this.showSubpages) {
				return this.visibleSubpages(this.page.id)
			}
			return []
		},

		hasVisibleSubpages() {
			return !!this.visibleSubpages(this.page.id).length
		},

		disableSorting() {
			return this.filteredView
		},
	},

	watch: {
		// Reinitate collapsed state when route changes
		pageParam: function() {
			this.initCollapsed()
		},

		pageId: function() {
			this.initCollapsed()
		},
	},

	mounted() {
		this.initCollapsed()
	},

	methods: {
		...mapActions(useRootStore, ['show']),
		...mapActions(usePagesStore, [
			'collapse',
			'expand',
		]),

		initCollapsed() {
			// Expand subpages if they're in the path to currentPage
			if (this.currentPageIds.includes(this.page.id)) {
				this.expand(this.page.id)
			}
		},
	},
}

</script>

<style>
.page-list-indent {
	padding-left: 20px;
}
</style>
