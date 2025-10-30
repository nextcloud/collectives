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
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { listen } from '@nextcloud/notify_push'
import { mapActions, mapState } from 'pinia'
import LandingPageWidgets from './Page/LandingPageWidgets.vue'
import PageTags from './Page/PageTags.vue'
import PageTitleBar from './Page/PageTitleBar.vue'
import SearchDialog from './Page/SearchDialog.vue'
import TextEditor from './Page/TextEditor.vue'
import { useNetworkState } from '../composables/useNetworkState.ts'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useTagsStore } from '../stores/tags.js'

export default {
	name: 'PageContainer',

	components: {
		LandingPageWidgets,
		PageTags,
		PageTitleBar,
		TextEditor,
		SearchDialog,
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	data() {
		return {
			loadPending: true,
		}
	},

	computed: {
		...mapState(useRootStore, [
			'done',
			'isTextEdit',
			'load',
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

	watch: {
		'currentPage.id': function() {
			this.setAttachmentsError(false)
			this.setAttachmentsLoaded(false)
			this.getAttachmentsForPage(true)
		},

		networkOnline: function(val) {
			if (val && this.loadPending) {
				this.getAttachmentsForPage(true)
			}
		},
	},

	mounted() {
		this.getAttachmentsForPage(true)
		// Reload attachment list on event from Text
		subscribe('collectives:text-image-node:add', this.getAttachmentsForPage)
		subscribe('text:image-node:add', this.getAttachmentsForPage)
		subscribe('collectives:page-sidebar', this.toggleSidebar)

		// Reload attachment list on filesystem changes
		listen('notify_file', this.getAttachmentsForPage.bind(this))
	},

	beforeDestroy() {
		unsubscribe('collectives:text-image-node:add', this.getAttachmentsForPage)
		unsubscribe('text:image-node:add', this.getAttachmentsForPage)
		unsubscribe('collectives:page-sidebar', this.toggleSidebar)
	},

	methods: {
		...mapActions(useRootStore, [
			'hide',
			'setActiveSidebarTab',
			'show',
		]),

		...mapActions(usePagesStore, [
			'getAttachments',
			'setAttachmentsError',
			'setAttachmentsLoaded',
		]),

		focusEditor() {
			this.$refs.texteditor.focusEditor()
		},

		saveEditor() {
			if (this.isTextEdit) {
				this.$refs.texteditor.save()
			}
		},

		/**
		 * Get attachments for current page
		 *
		 * @param {boolean} setLoading Whether to set loading attribute
		 */
		async getAttachmentsForPage(setLoading) {
			this.loadPending = true
			if (!this.networkOnline) {
				return
			}

			if (setLoading) {
				this.load('attachments')
			}
			try {
				await this.getAttachments(this.currentPage)
				this.setAttachmentsLoaded(true)
				this.loadPending = false
			} catch (e) {
				this.setAttachmentsError(true)
				console.error('Failed to get page attachments', e)
			} finally {
				this.done('attachments')
			}
		},

		toggleSidebar({ open, tab }) {
			if (open) {
				this.show('sidebar')
			} else {
				this.hide('sidebar')
			}
			this.setActiveSidebarTab(tab)
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
