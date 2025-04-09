<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :class="[currentPage.isFullWidth ? 'full-width-view' : 'sheet-view']">
		<PageTitle @focus-editor="focusEditor" @save-editor="saveEditor" />
		<div class="page-scroll-container">
			<LandingPageWidgets v-if="isLandingPage" />
			<TextEditor :key="`text-editor-${currentPage.id}`" ref="texteditor" />
		</div>
		<SearchDialog :show="shouldShowSearchDialog" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { usePagesStore } from '../stores/pages.js'
import { useSearchStore } from '../stores/search.js'

import LandingPageWidgets from './Page/LandingPageWidgets.vue'
import PageTitle from './Page/PageTitle.vue'
import SearchDialog from './Page/SearchDialog.vue'
import TextEditor from './Page/TextEditor.vue'

export default {
	name: 'Page',

	components: {
		LandingPageWidgets,
		PageTitle,
		TextEditor,
		SearchDialog,
	},

	computed: {
		...mapState(usePagesStore, [
			'currentPage',
			'isLandingPage',
		]),
		...mapState(useSearchStore, [
			'shouldShowSearchDialog',
		]),
	},

	methods: {
		focusEditor() {
			this.$refs.texteditor.focusEditor()
		},

		saveEditor() {
			this.$refs.texteditor.save()
		},
	},
}
</script>

<style lang="scss" scoped>
.page-scroll-container {
	overflow-y: auto;
	flex-grow: 1;
}
</style>

<style lang="scss">
@import '../css/editor';
</style>
