<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-container">
		<PageTitleBar :is-full-width="isFullWidth"
			@focus-editor="focusEditor"
			@save-editor="saveEditor" />
		<div class="page-scroll-container">
			<LandingPageWidgets v-if="isLandingPage" :is-full-width="isFullWidth" />
			<TextEditor :key="`text-editor-${currentPage.id}`" ref="texteditor" :is-full-width="isFullWidth" />
		</div>
		<SearchDialog :show="shouldShowSearchDialog" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { usePagesStore } from '../stores/pages.js'
import { useSearchStore } from '../stores/search.js'

import LandingPageWidgets from './Page/LandingPageWidgets.vue'
import PageTitleBar from './Page/PageTitleBar.vue'
import SearchDialog from './Page/SearchDialog.vue'
import TextEditor from './Page/TextEditor.vue'

export default {
	name: 'Page',

	components: {
		LandingPageWidgets,
		PageTitleBar,
		TextEditor,
		SearchDialog,
	},

	computed: {
		...mapState(useRootStore, [
			'isTextEdit',
		]),
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
	overflow-y: auto;

	// Make search dialog stick to the bottom
	flex-grow: 1;
}
</style>

<style lang="scss">
@import '../css/editor';
</style>
