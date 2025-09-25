<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<draggable
		:list="list"
		:component-data="getComponentData()"
		:data-parent-id="parentId"
		:disabled="disabled"
		:group="{ name: 'page-list', pull: true, put: true }"
		draggable=".page-list-drag-item"
		filter=".page-list-nodrag-item"
		:sort="allowSorting"
		:revert-on-spill="revertOnSpill"
		:fallback-tolerance="5"
		:animation="200"
		:delay="500"
		:delay-on-touch-only="true"
		:touch-start-threshold="3"
		:invert-swap="true"
		:swap-threshold="0.65"
		:empty-insert-threshold="4"
		direction="vertical"
		:set-data="setData"
		:move="onMove"
		@update="onUpdate"
		@add="onAdd"
		@end="onEnd">
		<template #header>
			<slot name="header" />
		</template>
		<slot />
	</draggable>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import draggable from 'vuedraggable'
import pageMixin from '../../mixins/pageMixin.js'
import { usePagesStore } from '../../stores/pages.js'

export default {
	name: 'DraggableElement',

	components: {
		draggable,
	},

	mixins: [
		pageMixin,
	],

	props: {
		list: {
			type: Array,
			required: true,
		},

		parentId: {
			type: Number,
			required: true,
		},

		disableSorting: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			sortableActive: false,
			dragoverPageId: 0,
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'disableDragndropSortOrMove',
			'sortByOrder',
		]),

		allowSorting() {
			// Disable sorting with alternative page orders
			return this.sortByOrder === 'byOrder'
		},

		disabled() {
			// IMPORTANT: needs to be synchronized with custom drag/drop events in PageListItem.vue
			return this.disableDragndropSortOrMove
				// Disable during Sortable move/sort operation
				|| this.sortableActive
				// Disable if disabled by parent component (e.g. in filtered view)
				|| this.disableSorting
		},

		revertOnSpill() {
			// TODO: revertOnSpill on nested sublists is broken with `sort: false`
			//       see https://github.com/SortableJS/Sortable/issues/2177
			return this.allowSorting
		},
	},

	methods: {
		...mapActions(usePagesStore, ['setHighlightPageId']),

		getComponentData() {
			return {
				on: {
					change: this.onChange,
				},
			}
		},

		setData(dataTransfer, dragEl) {
			dataTransfer.setData('pageId', dragEl.firstChild.dataset.pageId)
		},

		// Dragged element changes position
		onChange(ev) {
			// Highlight direct parent page when moving between subpages
			this.setHighlightPageId(null)
			if (ev.to !== ev.from) {
				this.setHighlightPageId(Number(ev.to.dataset.parentId))
			}
		},

		// Dragged element is moved inside list or between lists
		onMove(ev) {
			this.dragoverPageId = ev.related.dataset.pageId || ev.related.dataset.parentId

			// Force-move items to the end of the list if sorting is disabled (not effective for now, see `disabled()` method)
			if (!this.allowSorting) {
				if (ev.to !== ev.from) {
					ev.to.append(ev.dragged)
					return false
				}
			}
		},

		// Dragged element changes position inside a list
		onUpdate(ev) {
			// Sorting in one list
			this.sortableActive = true
			const pageId = Number(ev.originalEvent.dataTransfer.getData('pageId'))
			const parentId = Number(ev.to.dataset.parentId)
			this.subpageOrderUpdate(parentId, pageId, ev.newDraggableIndex)
			this.sortableActive = false
		},

		// Dragged element is added to another list
		onAdd(ev) {
			// Moving from one list to another
			this.sortableActive = true
			const pageId = Number(ev.originalEvent.dataTransfer.getData('pageId'))
			const oldParentId = Number(ev.from.dataset.parentId)
			const newParentId = Number(ev.to.dataset.parentId)
			let index = ev.newDraggableIndex
			// Force-move items to the end of the list if sorting is disabled
			if (!this.allowSorting) {
				index = Infinity
			}
			this.expand(newParentId)
			this.move(oldParentId, newParentId, pageId, index)
			this.sortableActive = false
		},

		// Element stops being dragged
		onEnd() {
			this.setHighlightPageId(null)
		},
	},
}
</script>

<style lang="scss" scoped>
// drag element in sortable.js lists
:deep(.sortable-ghost) {
	opacity: 0.7;
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}
</style>
