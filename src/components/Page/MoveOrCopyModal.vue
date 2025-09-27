<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<PagePicker
		:page-id="pageId"
		:parent-id="parentId"
		:is-copying="copying"
		:is-moving="moving"
		@copy="onCopy"
		@move="onMove"
		@close="onClose" />
</template>

<script>
import PagePicker from './PagePicker.vue'
import pageMixin from '../../mixins/pageMixin.js'

export default {
	name: 'MoveOrCopyModal',

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
			copying: false,
			moving: false,
		}
	},

	methods: {
		onClose() {
			this.$emit('close')
		},

		/**
		 * @param {object} object Parameter object
		 * @param {number} object.collectiveId collective ID
		 * @param {number} object.parentId new parent page for page
		 * @param {number} object.newIndex new order index of page
		 */
		async onCopy({ collectiveId, parentId, newIndex }) {
			this.copying = true

			if (collectiveId !== this.currentCollective.id) {
				this.copyToCollective(collectiveId, this.parentId, parentId, this.pageId, newIndex)
			} else {
				this.copy(this.parentId, parentId, this.pageId, newIndex)
			}

			this.copying = false
			this.$emit('close')
		},

		/**
		 * @param {object} object Parameter object
		 * @param {number} object.collectiveId collective ID
		 * @param {number} object.parentId new parent page for page
		 * @param {number} object.newIndex new order index of page
		 */
		async onMove({ collectiveId, parentId, newIndex }) {
			this.moving = true

			if (collectiveId !== this.currentCollective.id) {
				this.moveToCollective(collectiveId, this.parentId, parentId, this.pageId, newIndex)
			} else if (parentId !== this.parentId) {
				this.move(this.parentId, parentId, this.pageId, newIndex)
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
