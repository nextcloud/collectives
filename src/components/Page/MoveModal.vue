<template>
	<PagePicker :page-id="pageId"
		:parent-id="parentId"
		:loading="moving"
		@select="onMove"
		@close="onClose" />
</template>

<script>
import PagePicker from './PagePicker.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'MoveModal',

	components: {
		PagePicker,
	},

	mixins: [
		pageMixin,
	],

	props: {
		pageId: {
			type: Number,
			required: true,
		},
		parentId: {
			type: Number,
			required: true,
		},
	},

	data() {
		return {
			moving: false,
		}
	},

	methods: {
		onClose() {
			this.$emit('close')
		},

		/**
		 *
		 * @param {object} object Parameter object
		 * @param {number} object.parentId new parent page for page
		 * @param {number} object.newIndex new order index of page
		 */
		async onMove({ parentId, newIndex }) {
			this.moving = true

			if (parentId !== this.parentId) {
				// Move page to new parent
				this.movePage(this.parentId, parentId, this.pageId, newIndex)
			} else {
				// Change subpage order of current parent
				this.subpageOrderUpdate(this.parentId, this.pageId, newIndex)
			}

			this.moving = false
			this.$emit('close')
		},
	},
}
</script>

<style scoped>
</style>
