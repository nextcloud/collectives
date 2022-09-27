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

	methods: {
		ready() {
			this.$nextTick(() => {
				this.$emit('ready')
			})
		},
	},
}

</script>

<style lang="scss">
[data-text-el='editor-container'] .document-status {
	max-width: 670px;
	padding: 0 2px;
	margin: auto;
}

[data-text-el='editor-container'] .editor--outline {
	top: revert !important;
}
</style>
