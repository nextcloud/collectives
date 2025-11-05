<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		ref="textContainer"
		class="collectives-text-container"
		:class="[isFullWidth ? 'full-width-view' : 'sheet-view']">
		<SkeletonLoading
			v-show="!contentLoaded"
			type="text"
			class="page-content-skeleton" />
		<div
			v-show="contentLoaded && !showEditor"
			ref="readerEl"
			data-collectives-el="reader"
			data-cy-collectives="reader" />
		<div
			v-if="currentCollectiveCanEdit"
			v-show="contentLoaded && showEditor"
			ref="editorEl"
			data-collectives-el="editor"
			data-cy-collectives="editor" />
	</div>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { useElementSize } from '@vueuse/core'
import { mapActions, mapState } from 'pinia'
import { ref, watch } from 'vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import { useEditor } from '../../composables/useEditor.js'
import { useReader } from '../../composables/useReader.js'
import { editorApiUpdateReadonlyBarProps } from '../../constants.js'
import pageContentMixin from '../../mixins/pageContentMixin.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { useVersionsStore } from '../../stores/versions.js'

export default {
	name: 'TextEditor',

	components: {
		SkeletonLoading,
	},

	mixins: [
		pageContentMixin,
	],

	props: {
		isFullWidth: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const textContainer = ref(null)
		const { width } = useElementSize(textContainer)
		watch(width, (value) => {
			document.documentElement.style.setProperty('--text-container-width', value + 'px')
		})
		const davContent = ref('')
		const { contentLoaded, editor, editorContent, editorEl, pageContent, setupEditor } = useEditor(davContent)
		const { pageInfoBarPage, reader, readerEl, setupReader } = useReader(pageContent)
		return { contentLoaded, davContent, editor, editorContent, editorEl, pageContent, pageInfoBarPage, reader, readerEl, setupEditor, setupReader, textContainer, width }
	},

	data() {
		return {
			textEditWatcher: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['editorApiFlags', 'isPublic', 'loading']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
		]),

		...mapState(usePagesStore, [
			'attachments',
			'backlinks',
			'currentPage',
			'currentPageDavUrl',
			'isTextEdit',
		]),

		showEditor() {
			return this.currentCollectiveCanEdit && !this.loading('editor') && this.isTextEdit
		},
	},

	watch: {
		'currentPage.timestamp': function(value) {
			if (value) {
				// Update currentPage in PageInfoBar component through Text editorAPI
				if (this.editorApiFlags.includes(editorApiUpdateReadonlyBarProps)) {
					const readerPage = this.pageInfoBarPage || this.currentPage
					this.reader?.updateReadonlyBarProps({
						currentPage: readerPage,
						attachmentCount: this.attachments.length,
						backlinkCount: this.backlinks(readerPage.id).length,
					})
				}

				this.getPageContent()
			}
		},
	},

	beforeMount() {
		// Change back to default view mode
		this.setTextView()

		this.load('editor')
		this.load('pageContent')
	},

	async mounted() {
		const readerPromise = this.setupReader(this.currentPage)
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
		...mapActions(useRootStore, ['load', 'done']),
		...mapActions(useVersionsStore, ['getVersions']),
		...mapActions(usePagesStore, ['setTextEdit', 'setTextView', 'touchPage']),

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
				// for new pages
				|| this.loading('newPageContent')
				// or when page is empty
				|| !this.davContent.trim()) {
				this.setTextEdit()
				this.done('newPageContent')
			}
		},

		// called from the parent component as well
		focusEditor() {
			this.editor?.focus()
		},

		// called from the parent component as well
		saveEditor() {
			return this.editor.save()
		},

		async stopEdit() {
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
				// Save pending changes in editor
				// TODO: detect missing connection and display warning
				await this.saveEditor()
					.catch(() => {
						showError(t('collectives', 'Error saving the document. Please try again.'))
						this.setTextEdit()
					})

				// Touch page to update last changed timestamp
				this.touchPage()

				// Update loaded versions
				if (!this.isPublic && this.hasVersionsLoaded) {
					this.getVersions(this.currentPage.id)
				}
			}
		},

		async getPageContent() {
			this.davContent = await this.fetchPageContent(this.currentPageDavUrl)
			this.done('pageContent')
		},
	},
}
</script>

<style lang="scss" scoped>
.collectives-text-container {
	display: flex;
	flex-direction: column;

	// Give editor some minimum scroll height on empty/short content
	// Important on landing page when landing page widgets cover full height
	min-height: 50vh;
}

.page-content-skeleton {
	padding-block-start: var(--default-clickable-area);
}

@media print {
	/* Don't print unwanted elements */
	.collectives-text-container {
		overflow: visible;
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
