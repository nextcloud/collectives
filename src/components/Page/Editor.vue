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
		:show-outline-outside="showOutline"
		mime="text/markdown"
		class="file-view active"
		@ready="ready"
		@outline-toggled="toggleOutlineFromText" />
</template>

<script>
import { mapGetters, mapMutations } from 'vuex'

export default {
	name: 'Editor',

	computed: {
		...mapGetters([
			'currentPage',
			'currentPageFilePath',
			'shareTokenParam',
			'showing',
		]),

		/**
		 * Fetch text app handler from Viewer app
		 *
		 * @return {object}
		 */
		handler() {
			return OCA.Viewer.availableHandlers.find(h => h.id === 'text')
		},

		showOutline() {
			return this.showing('outline')
		},
	},

	methods: {
		...mapMutations([
			'hide',
			'show',
			'toggle',
		]),

		ready() {
			this.$nextTick(() => {
				this.$emit('ready')
			})
		},

		toggleOutlineFromText(visible) {
			if (visible === true) {
				this.show('outline')
			} else if (visible === false) {
				this.hide('outline')
			}
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
