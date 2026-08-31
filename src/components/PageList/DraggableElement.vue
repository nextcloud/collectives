<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<VueDraggable
		:modelValue="list"
		:data-parent-id="parentId"
		:disabled="disabled"
		:class="{ 'dragover-target-active': isDragoverTargetPage }"
		:group="{ name: 'page-list', pull: true, put: true }"
		draggable=".page-list-drag-item"
		:sort="allowSorting"
		:revertOnSpill="true"
		:fallbackTolerance="5"
		:animation="200"
		:delay="500"
		:delayOnTouchOnly="true"
		:touchStartThreshold="3"
		:invertSwap="true"
		:swapThreshold
		:emptyInsertThreshold="4"
		direction="vertical"
		@change="onChange"
		@move="onMove"
		@update="onUpdate"
		@add="onAdd"
		@end="onEnd">
		<template #header>
			<slot name="header" />
		</template>
		<slot />
	</VueDraggable>
</template>

<script lang="ts">
import type { PropType } from 'vue'
import type { DraggableEvent, MoveEvent } from 'vue-draggable-plus'
import type { PageInfo } from '../../types.ts'

import { mapActions, mapState } from 'pinia'
import { defineComponent } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import pageMixin from '../../mixins/pageMixin.js'
import { usePagesStore } from '../../stores/pages.js'

// Direction to insert the dragged element in relative to the related element
type SwapDirection = boolean | -1 | 1 | undefined

export default defineComponent({
	name: 'DraggableElement',

	components: {
		VueDraggable,
	},

	mixins: [
		pageMixin,
	],

	props: {
		list: {
			type: Array as PropType<PageInfo[]>,
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
			swapThreshold: 0.65,
		}
	},

	computed: {
		...mapState(usePagesStore, [
			'disableDragndropSortOrMove',
			'draggedPageId',
			'isDragoverTargetPage',
			'pageParents',
			'sortByOrder',
		]),

		allowSorting(): boolean {
			// Disable sorting with alternative page orders
			return this.sortByOrder === 'byOrder'
		},

		disabled(): boolean {
			// IMPORTANT: needs to be synchronized with custom drag/drop events in PageListItem.vue
			return this.disableDragndropSortOrMove
				// Disable if disabled by parent component (e.g. in filtered view)
				|| this.disableSorting
		},
	},

	methods: {
		...mapActions(usePagesStore, ['setHighlightPageId']),

		// Dragged element changes position
		onChange(event: DraggableEvent) {
			// Highlight direct parent page when moving between subpages
			this.setHighlightPageId(null)
			if (event.to !== event.from) {
				this.setHighlightPageId(Number(event.to.dataset.parentId))
			}
		},

		// Dragged element is moved inside list or between lists
		onMove(event: MoveEvent, originalEvent: Event) {
			// Reject moving a page into itself or one of its own descendants
			// IMPORTANT: needs to be synchronized with `isPotentialDropTarget` in PageListItem.vue
			const targetParentId = Number(event.to.dataset.parentId)
			if (this.draggedPageId && this.pageParents(targetParentId).some((page: PageInfo) => page.id === this.draggedPageId)) {
				return false
			}

			// Force-move items to the end of the list if sorting is disabled (not effective for now, see `disabled()` method)
			if (!this.allowSorting) {
				if (event.to !== event.from) {
					event.to.append(event.dragged)
					return false
				}
			}

			return this.swapDirection(event, (originalEvent as MouseEvent).clientY)
		},

		// Recompute the swap direction relative to the page row of the related element.
		//
		// Sortable.js derives the swap zones from the bounding box of the related element.
		// For an expanded page that box spans the entire subtree, so the `insert before`
		// zone covers the whole page row and the ghost jumps above the page as soon as
		// it's dragged onto it. Deriving the zones from the page row instead restores the
		// neutral zone in the middle of the row that's needed to drop a page into it.
		swapDirection(event: MoveEvent, posY: number): SwapDirection {
			const row = event.related.querySelector(':scope > .app-navigation-entry')

			// Only handle swaps with the pointer inside the related element
			if (!row || posY < event.relatedRect.top || posY > event.relatedRect.bottom) {
				return
			}

			// Pointer is next to the subpages of an expanded page, not on the page row itself
			const rowRect = row.getBoundingClientRect()
			if (posY < rowRect.top || posY > rowRect.bottom) {
				return false
			}

			// Same swap zone that Sortable.js applies to a page without visible subpages
			const margin = rowRect.height * this.swapThreshold / 2
			if (posY < rowRect.top + margin) {
				return -1
			}

			// Subpages are only rendered while the page is expanded
			const hasVisibleSubpages = !!event.related.querySelector('.page-list-drag-item')

			// Inserting after a page with visible subpages would move the ghost below its
			// entire subtree, far away from the pointer. Keep that zone neutral instead,
			// it's used to drop the dragged page into the page anyway.
			if (posY > rowRect.bottom - margin && !hasVisibleSubpages) {
				return 1
			}

			// Neutral zone in the middle of the page row: leave the ghost where it is
			return false
		},

		// Dragged element changes position inside a list
		onUpdate(event: DraggableEvent) {
			// Sorting in one list
			const pageId = Number(event.item.dataset.pageId)
			const parentId = Number(event.to.dataset.parentId)
			this.subpageOrderUpdate(parentId, pageId, event.newDraggableIndex ?? 0)
		},

		// Dragged element is added to another list
		onAdd(event: DraggableEvent) {
			const pageId = Number(event.item.dataset.pageId)
			const oldParentId = Number(event.from.dataset.parentId)
			const newParentId = Number(event.to.dataset.parentId)

			// Reject moving a page into itself or one of its own descendants
			// IMPORTANT: needs to be synchronized with `isPotentialDropTarget` in PageListItem.vue
			if (this.pageParents(newParentId).some((page: PageInfo) => page.id === pageId)) {
				event.from.append(event.item)
				return
			}

			// Moving from one list to another
			let index = event.newDraggableIndex ?? Infinity
			// Force-move items to the end of the list if sorting is disabled
			if (!this.allowSorting) {
				index = Infinity
			}
			this.expand(newParentId)
			this.move(oldParentId, newParentId, pageId, index)
		},

		// Element stops being dragged
		onEnd() {
			this.setHighlightPageId(null)
		},
	},
})
</script>

<style lang="scss" scoped>
// drag element in sortable.js lists
:deep(.sortable-ghost) {
	opacity: 0.7;
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}

.dragover-target-active :deep(.sortable-ghost) {
	opacity: 0.2;
}
</style>
