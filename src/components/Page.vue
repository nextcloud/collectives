<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-container">
		<PageTitleBar
			:is-full-width="isFullWidth"
			@focus-editor="focusEditor"
			@save-editor="saveEditor" />
		<PageTags v-if="tagsLoaded" :is-full-width="isFullWidth" />
		<div class="page-scroll-container">
			<LandingPageWidgets v-if="isLandingPage" :is-full-width="isFullWidth" />
			<TextEditor :key="`text-editor-${currentPage.id}`" ref="texteditor" :is-full-width="isFullWidth" />
		</div>
		<SearchDialog :show="shouldShowSearchDialog" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import LandingPageWidgets from './Page/LandingPageWidgets.vue'
import PageTags from './Page/PageTags.vue'
import PageTitleBar from './Page/PageTitleBar.vue'
import SearchDialog from './Page/SearchDialog.vue'
import TextEditor from './Page/TextEditor.vue'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useTagsStore } from '../stores/tags.js'

export default {
	name: 'Page',

	components: {
		LandingPageWidgets,
		PageTags,
		PageTitleBar,
		TextEditor,
		SearchDialog,
	},

	computed: {
		...mapState(useRootStore, [
			'isTextEdit',
		]),

		...mapState(useTagsStore, ['tagsLoaded']),
		...mapState(usePagesStore, [
			'currentPage',
			'isLandingPage',
		]),

		...mapState(useSearchStore, [
			'shouldShowSearchDialog',
		]),

		isFullWidth() {
			return this.currentPage.isFullWidth
		},
	},

	methods: {
		focusEditor() {
			this.$refs.texteditor.focusEditor()
		},

		saveEditor() {
			if (this.isTextEdit) {
				this.$refs.texteditor.save()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.page-container {
	// Required for search dialog to stick to the bottom
	height: 100%;
	display: flex;
	flex-direction: column;
}

.page-scroll-container {
	display: flex;
	flex-direction: column;
	overflow-y: auto;

	// Make search dialog stick to the bottom
	flex-grow: 1;

	// Menubar overlays headings when scrolling to them otherwise
	scroll-padding-top: calc(var(--default-clickable-area) + 8px + 1px);
}
</style>

<style lang="scss">
@use '../css/editor';
</style>
