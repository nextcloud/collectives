<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div>
		<WidgetHeading v-if="isLandingPage"
			:title="t('collectives', 'Landing page')"
			class="text-container-heading" />
		<SkeletonLoading v-show="!contentLoaded"
			type="text"
			class="page-content-skeleton" />
		<div v-show="contentLoaded && !showEditor"
			ref="reader"
			data-collectives-el="reader" />
		<div v-if="currentCollectiveCanEdit"
			v-show="contentLoaded && showEditor"
			ref="editor"
			data-collectives-el="editor" />
	</div>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import WidgetHeading from './LandingPageWidgets/WidgetHeading.vue'
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useVersionsStore } from '../../stores/versions.js'
import editorMixin from '../../mixins/editorMixin.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'
import SkeletonLoading from '../SkeletonLoading.vue'

export default {
	name: 'TextEditor',

	components: {
		SkeletonLoading,
		WidgetHeading,
	},

	mixins: [
		editorMixin,
		pageContentMixin,
	],

	data() {
		return {
			textEditWatcher: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'isTextEdit', 'loading']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
		]),
		...mapState(usePagesStore, [
			'currentPage',
			'currentPageDavUrl',
			'isLandingPage',
			'isTemplatePage',
		]),

		showEditor() {
			return this.currentCollectiveCanEdit && !this.loading('editor') && this.isTextEdit
		},
	},

	watch: {
		'currentPage.timestamp'() {
			this.getPageContent()
		},
	},

	beforeMount() {
		// Change back to default view mode
		this.setTextView()

		this.load('editor')
		this.load('pageContent')
	},

	async mounted() {
		const readerPromise = this.setupReader()
		const editorPromise = this.setupEditor()
		const pageContentPromise = this.getPageContent()
		Promise.all([readerPromise, editorPromise, pageContentPromise]).then(() => {
			this.initEditMode()
		})

		this.textEditWatcher = this.$watch('isTextEdit', (val) => {
			if (val === false) {
				this.stopEdit()
			}
		})
		subscribe('collectives:attachment:restore', this.restoreAttachment)
	},

	beforeDestroy() {
		unsubscribe('collectives:attachment:restore', this.restoreAttachment)
		this.textEditWatcher()
	},

	methods: {
		...mapActions(useRootStore, [
			'load',
			'done',
			'setTextEdit',
			'setTextView',
		]),
		...mapActions(useVersionsStore, ['getVersions']),
		...mapActions(usePagesStore, ['touchPage']),

		restoreAttachment(name) {
			// inspired by the fixedEncodeURIComponent function suggested in
			// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
			const src = '.attachments.' + this.currentPage.id + '/' + name
			// simply get rid of brackets to make sure link text is valid
			// as it does not need to be unique and matching the real file name
			const alt = name.replaceAll(/[[\]]/g, '')

			this.editor.insertAtCursor(`<img src="${src}" alt="${alt}" />`)
		},

		initEditMode() {
			// Open in edit mode when pageMode is set
			if (!!this.currentCollective.pageMode
				// for template pages
				|| this.isTemplatePage
				// for new pages
				|| this.loading('newPageContent')
				// or when page is empty
				|| !this.davContent.trim()) {
				this.setTextEdit()
				this.done('newPageContent')
			}
		},

		stopEdit() {
			// switch back to edit if there's no content
			if (!this.pageContent?.trim()) {
				this.setTextEdit()
				this.$nextTick(() => {
					this.focusEditor()
				})
				return
			}

			const changed = this.editorContent && (this.editorContent !== this.davContent)
			if (changed) {
				this.touchPage()
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.getVersions(this.currentPage.id)
				}

				// Save pending changes in editor
				// TODO: detect missing connection and display warning
				this.save()
					.catch(() => {
						showError(t('collectives', 'Error saving the document. Please try again.'))
						this.setTextEdit()
					})
			}
		},

		async getPageContent() {
			this.davContent = await this.fetchPageContent(this.currentPageDavUrl)
			this.reader?.setContent(this.pageContent)
			this.done('pageContent')
		},
	},
}
</script>

<style lang="scss" scoped>
.text-container-heading {
	padding-left: 14px;
}

.page-content-skeleton {
	padding-top: var(--default-clickable-area);
}

:deep([data-text-el='editor-container']) {
	/* Remove scrolling mechanism from editor-container, required for menubar stickyness */
	overflow: visible;

	div.editor {
		/* Adjust to page titlebar height */
		div.text-menubar {
			margin: auto;
			top: 59px;
		}
	}
}

@media print {
	/* Don't print unwanted elements */
	.text-container-heading {
		display: none !important;
	}
}
</style>

<style lang="scss">
@media print {
	h1, h2, h3 {
		page-break-after: avoid;
		break-after: avoid;
	}
}
</style>
