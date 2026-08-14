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
import { t } from '@nextcloud/l10n'
import { useElementSize } from '@vueuse/core'
import { mapActions, mapState } from 'pinia'
import { ref, watch } from 'vue'
import SkeletonLoading from '../SkeletonLoading.vue'
import { useEditor } from '../../composables/useEditor.ts'
import { useReader } from '../../composables/useReader.ts'
import pageContentMixin from '../../mixins/pageContentMixin.js'
import { useCirclesStore } from '../../stores/circles.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { encodeAttachmentFilename } from '../../util/attachmentFilename.ts'

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
		const { contentLoaded, destroyEditor, editor, editorContent, editorEl, pageContent, setupEditor } = useEditor(davContent)
		const { pageInfoBarPage, reader, readerEl, setupReader } = useReader(pageContent)
		return { contentLoaded, davContent, destroyEditor, editor, editorContent, editorEl, pageContent, pageInfoBarPage, reader, readerEl, setupEditor, setupReader, textContainer, width }
	},

	data() {
		return {
			editorSetupPromise: null,
			textEditWatcher: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
		]),

		...mapState(usePagesStore, [
			'currentPage',
			'currentPageDavUrl',
			'isTextEdit',
		]),

		showEditor() {
			return this.currentCollectiveCanEdit
				&& this.editor
				&& !this.loading('editor')
				&& this.isTextEdit
		},
	},

	watch: {
		'currentPage.timestamp': function(value) {
			if (value) {
				this.getPageContent()
			}
		},
	},

	beforeMount() {
		// Change back to default preview mode
		this.setTextPreview()

		this.load('editor')
		this.load('pageContent')
	},

	async mounted() {
		const readerPromise = this.setupReader(this.currentPage)
		const pageContentPromise = this.getPageContent()

		this.textEditWatcher = this.$watch('isTextEdit', async (val) => {
			if (!val) {
				await this.stopEdit()
				return
			}

			const editorPromise = this.ensureEditor()
			const circlesStore = useCirclesStore()
			if (!circlesStore.currentCircleMembersFullyLoaded && !this.isPublic) {
				await Promise.all([
					editorPromise,
					this.getCircleMembers(this.currentCollective.circleId),
				])
			} else {
				await editorPromise
			}
		})
		subscribe('collectives:attachment:insert', this.insertAttachment)
		subscribe('collectives:attachment:replaceFilename', this.replaceAttachmentFilename)
		subscribe('collectives:attachment:removeReferences', this.removeAttachmentReferences)

		await Promise.all([readerPromise, pageContentPromise])
		this.initEditMode()
		if (this.isTextEdit) {
			await this.ensureEditor()
		} else {
			this.done('editor')
		}
	},

	beforeUnmount() {
		unsubscribe('collectives:attachment:removeReferences', this.removeAttachmentReferences)
		unsubscribe('collectives:attachment:replaceFilename', this.replaceAttachmentFilename)
		unsubscribe('collectives:attachment:insert', this.insertAttachment)
		this.textEditWatcher()
	},

	methods: {
		t,

		...mapActions(useRootStore, ['load', 'done']),
		...mapActions(usePagesStore, ['setTextEdit', 'setTextPreview', 'touchPage']),
		...mapActions(useCirclesStore, ['getCircleMembers']),

		async ensureEditor() {
			if (this.editor) {
				return
			}
			const setupPromise = this.editorSetupPromise ?? (() => {
				this.load('editor')
				return this.setupEditor()
			})()
			this.editorSetupPromise = setupPromise
			try {
				await setupPromise
			} finally {
				if (this.editorSetupPromise === setupPromise) {
					this.editorSetupPromise = null
				}
			}
			if (!this.isTextEdit && this.editor) {
				await this.destroyEditor()
			}
		},

		insertAttachment({ name }) {
			// inspired by the fixedEncodeURIComponent function suggested in
			// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent
			const src = '.attachments.' + this.currentPage.id + '/' + encodeAttachmentFilename(name)
			// simply get rid of brackets to make sure link text is valid
			// as it does not need to be unique and matching the real file name
			const alt = name.replaceAll(/[[\]]/g, '')

			this.editor.insertAtCursor(`<img src="${src}" alt="${alt}" />`)
		},

		replaceAttachmentFilename({ pageId, oldName, newName }) {
			// Only available since editorApi 1.4
			if (this.editor.replaceAttachmentFilename) {
				this.editor.replaceAttachmentFilename(pageId, oldName, newName)
			}
		},

		removeAttachmentReferences({ pageId, name }) {
			// Only available since editorApi 1.4
			if (this.editor.removeAttachmentReferences) {
				this.editor.removeAttachmentReferences(pageId, name)
			}
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

				// Touch the page before reading the versions created by this save.
				try {
					await this.touchPage()
				} catch {
					// A failed metadata touch must not undo the completed document save.
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
	flex-grow: 1;

	// Give editor some minimum scroll height on empty/short content
	// Important on landing page when landing page widgets cover full height
	min-height: 50vh;
}

[data-collectives-el="reader"], [data-collectives-el="editor"] {
	display: flex;
	flex-grow: 1;
}

[data-collectives-el="reader"] {
	// Set default width for reader, required for read-only shares on Nextcloud <= 32
	:deep(.editor__content) {
		max-width: var(--text-editor-max-width, var(--text-editor-max-width-default));
	}
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
