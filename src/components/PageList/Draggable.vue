<template>
	<draggable :list="list"
		:component-data="getComponentData()"
		:data-parent-id="parentId"
		:disabled="disabled"
		:group="{ name: 'page-list', pull: true, put: true }"
		draggable=".page-list-drag-item"
		filter=".page-list-nodrag-item"
		:sort="allowSorting"
		:revert-on-spill="revertOnSpill"
		class="page-list-dragarea"
		:remove-clone-on-hide="false"
		:fallback-tolerance="5"
		:animation="200"
		:delay="500"
		:delay-on-touch-only="true"
		:touch-start-threshold="3"
		:invert-swap="true"
		:swap-threshold="0.65"
		:fallback-on-body="true"
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
import draggable from 'vuedraggable'
import pageMixin from '../../mixins/pageMixin.js'
import { mapGetters, mapMutations } from 'vuex'
import debounce from 'debounce'

export default {
	name: 'Draggable',

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
		isTemplate: {
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
		...mapGetters([
			'collapsed',
			'sortBy',
			'visibleSubpages',
		]),

		allowSorting() {
			// Disable sorting for templates and with alternative page orders
			return !this.isTemplate && (this.sortBy === 'byOrder')
		},

		disabled() {
			// Disable sortable during move/sort operation or if disabled by parent component (e.g. in filtered view)
			// return this.sortableActive || this.disableSorting

			// Also disable with alternative page orders for now.
			// TODO: Smoothen UX if allowed to move but not to sort with alternative page orders
			return this.sortableActive || this.disableSorting || (this.sortBy !== 'byOrder')
		},

		revertOnSpill() {
			// TODO: revertOnSpill on nested sublists is broken with `sort: false`
			//       see https://github.com/SortableJS/Sortable/issues/2177
			return this.allowSorting
		},
	},

	watch: {
		'dragoverPageId'(val, oldval) {
			this.onDragoverPage(val, oldval)
		},
	},

	methods: {
		...mapMutations([
			'setHighlightPageId',
		]),

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

		onChange(ev) {
			// Highlight direct parent page when moving between subpages
			this.setHighlightPageId(null)
			if (ev.to !== ev.from) {
				this.setHighlightPageId(Number(ev.to.dataset.parentId))
			}
		},

		onMove(ev, origEv) {
			this.dragoverPageId = ev.related.dataset.pageId || ev.related.dataset.parentId

			// Force-move items to the end of the list if sorting is disabled (not effective for now, see `disabled()` method)
			if (!this.allowSorting) {
				if (ev.to !== ev.from) {
					ev.to.append(ev.dragged)
					return false
				}
			}
		},

		onUpdate(ev) {
			// Sorting in one list
			this.sortableActive = true
			const pageId = Number(ev.originalEvent.dataTransfer.getData('pageId'))
			const parentId = Number(ev.to.dataset.parentId)
			this.subpageOrderUpdate(parentId, pageId, ev.newDraggableIndex)
			this.sortableActive = false
		},

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
			this.movePage(oldParentId, newParentId, pageId, index)
			this.sortableActive = false
		},

		onEnd(ev) {
			this.setHighlightPageId(null)
		},

		onDragoverPage: debounce(function(val, oldval) {
			if (val) {
				// Disable automatic expansion of subpages on hover for now. There's still too many UX glitches.
				// this.expand(val)
			}
		}, 1500),
	},
}
</script>

<style lang="scss" scoped>
// drag element in sortable.js lists
::v-deep .sortable-ghost {
	opacity: 0.7;
	border-radius: var(--border-radius-large);
	background-color: var(--color-background-hover);
}
</style>
