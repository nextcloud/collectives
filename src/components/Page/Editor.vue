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
		@ready="$emit('ready')" />
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
}

</script>

<style lang="scss">
#editor-container .document-status {
	width: 670px;
	padding: 0 2px;
	margin: auto;
}

#editor-container .editor__content {
	border: 2px solid var(--color-border);
	border-radius: var(--border-radius);
}
</style>
