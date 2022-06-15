<template>
	<component :is="handler.component"
		:key="`editor-${currentPage.id}`"
		:fileid="currentPage.id"
		:basename="currentPage.fileName"
		:filename="`/${currentPageFilePath}`"
		:has-preview="true"
		:active="true"
		:autofocus="false"
		:share-token="shareTokenParam"
		mime="text/markdown"
		class="file-view active"
		@ready="ready" />
</template>

<script>
import { mapGetters } from 'vuex'

export default {
	name: 'Editor',

	computed: {
		...mapGetters([
			'currentPage',
			'currentPageFilePath',
			'shareTokenParam',
		]),

		/**
		 * Fetch text app handler from Viewer app
		 *
		 * @return {object}
		 */
		handler() {
			return OCA.Viewer.availableHandlers.find(h => h.id === 'text')
		},
	},

	unmounted() {
		document.getElementById('editor-wrapper')?.removeEventListener('wheel', this.scrollEditorFromOutside)
	},

	methods: {
		ready() {
			this.$nextTick(() => {
				// scroll text div from outer text-wrapper div
				// Try to remove event listener first to cleanup earlier ones and prevent possible memory leaks
				document.getElementById('editor-wrapper').removeEventListener('wheel', this.scrollEditorFromOutside)
				document.getElementById('editor-wrapper').addEventListener('wheel', this.scrollEditorFromOutside)
				this.$emit('ready')
			})
		},

		scrollEditorFromOutside(e) {
			document.getElementById('editor').scrollBy(e.deltaX, e.deltaY)
		},
	},
}

</script>

<style lang="scss">
#editor-container .document-status {
	width: 670px;
	padding: 0 2px;
	margin: auto;
}
</style>
