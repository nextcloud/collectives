<template>
	<draggable :list="list"
		:data-parent-id="parentId"
		:disabled="disableDragging"
		:group="{ name: 'page-list', pull: true, put: true }"
		draggable=".page-list-drag-item"
		filter=".page-list-nodrag-item"
		:sort="allowSorting"
		:revert-on-spill="revertOnSpill"
		class="page-list-dragarea"
		:remove-clone-on-hide="false"
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
		@add="onAdd">
		<template #header>
			<slot name="header" />
		</template>
		<slot />
	</draggable>
</template>

<script>
import draggable from 'vuedraggable'
import pageMixin from '../../mixins/pageMixin.js'
import { mapGetters } from 'vuex'

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
		isTemplate: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			disableDragging: false,
		}
	},

	computed: {
		...mapGetters([
			'collapsed',
			'sortBy',
			'visibleSubpages',
		]),

		revertOnSpill() {
			// TODO: revertOnSpill on nested sublists is broken with `sort: false`
			//       see https://github.com/SortableJS/Sortable/issues/2177
			return this.allowSorting
		},

		allowSorting() {
			// Disable sorting for templates and with alternative page orders
			return !this.isTemplate && this.sortBy === 'byOrder'
		}
	},

	methods: {
		setData(dataTransfer, dragEl) {
			dataTransfer.setData('pageId', dragEl.firstChild.dataset.pageId)
		},

		onMove(ev, origEv) {
			// Expand subpage list
			const pageId = ev.related.dataset.pageId
			if (this.collapsed(pageId)) {
				this.expand(pageId)
			}

			if (!this.allowSorting) {
				// Force-move items to the end of the list if sorting is disabled
				if (ev.to !== ev.from) {
					ev.to.append(ev.dragged)
					return false
				}
			}
		},

		onUpdate(ev) {
			// Sorting in one list
			this.disableDragging = true
			const pageId = Number(ev.originalEvent.dataTransfer.getData('pageId'))
			const parentId = Number(ev.to.dataset.parentId)
			this.subpageOrderUpdate(parentId, pageId, ev.newDraggableIndex)
			this.disableDragging = false
		},

		onAdd(ev) {
			// Moving from one list to another
			this.disableDragging = true
			const pageId = Number(ev.originalEvent.dataTransfer.getData('pageId'))
			const oldParentId = Number(ev.from.dataset.parentId)
			const newParentId = Number(ev.to.dataset.parentId)
			this.movePage(oldParentId, newParentId, pageId, ev.newDraggableIndex)
			this.disableDragging = false
		},
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
