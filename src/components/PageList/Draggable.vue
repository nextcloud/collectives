<template>
	<draggable :list="list"
		:data-parent-page-id="parentPageId"
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
		:invert-swap="false"
		:swap-threshold="0.65"
		:fallback-on-body="true"
		:empty-insert-threshold="4"
		direction="vertical"
		:set-data="setData"
		:move="onMove"
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
		parentPageId: {
			type: Number,
			required: true,
		},
		allowSorting: {
			type: Boolean,
			default: false,
		},
		revertOnSpill: {
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
		]),
	},

	methods: {
		setData(dataTransfer, dragEl) {
			dataTransfer.setData('pageId', dragEl.firstChild.dataset.pageId)
		},

		onMove(ev, origEv) {
			const parentPageId = Number(ev.to.dataset.parentPageId)

			// Expand subpage list
			if (this.collapsed(parentPageId)) {
				console.debug('expand', parentPageId)
				this.expand(parentPageId)
			}

			if (!this.allowSorting) {
				// Force-move items to the end of the list if sorting is disabled
				if (ev.to !== ev.from) {
					ev.to.append(ev.dragged)
					return false
				}
			}
		},

		onAdd(ev) {
			this.disableDragging = true
			const pageId = Number(ev.originalEvent.dataTransfer.getData('pageId'))
			const parentPageId = Number(ev.to.dataset.parentPageId)
			this.movePage(parentPageId, pageId)
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
