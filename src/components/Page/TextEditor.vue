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
import escapeHtml from 'escape-html'
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
import { useVersionsStore } from '../../stores/versions.js'
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
		const { contentLoaded, editor, editorContent, editorEl, pageContent, setupEditor, saveEditor } = useEditor(davContent)
		const { pageInfoBarPage, reader, readerEl, setupReader } = useReader(pageContent)
		return { contentLoaded, davContent, editor, editorContent, editorEl, pageContent, pageInfoBarPage, reader, readerEl, setupEditor, saveEditor, setupReader, textContainer, width }
	},

	data() {
		return {
			textEditWatcher: null,
			mountedPageId: null,
			disposed: false,
			unregisterCurrentSnapshotPreparer: null,
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
			return this.currentCollectiveCanEdit && !this.loading('editor') && this.isTextEdit
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
		this.mountedPageId = this.currentPage.id
		this.unregisterCurrentSnapshotPreparer = this.registerCurrentSnapshotPreparer(() => this.prepareCurrentEditorSnapshot())
		const readerPromise = this.setupReader(this.currentPage)
		const editorPromise = this.setupEditor()
		const pageContentPromise = this.getPageContent()
		Promise.all([readerPromise, editorPromise, pageContentPromise]).then(() => {
			if (!this.disposed && this.currentPage.id === this.mountedPageId) {
				this.initEditMode()
			}
		}).catch((error) => {
			console.error('Failed to load page', error)
		})

		this.textEditWatcher = this.$watch('isTextEdit', async (val) => {
			if (val === false) {
				this.stopEdit()
			} else if (val === true) {
				try {
					await this.setupEditor()
				} catch {
					if (!this.disposed && this.currentPage.id === this.mountedPageId) {
						showError(t('collectives', 'Could not load the editor. Please try again.'))
						this.setTextPreview()
					}
					return
				}
				if (this.disposed || this.currentPage.id !== this.mountedPageId) {
					return
				}
				// Load full circle members for autocomplete when entering edit mode
				const circlesStore = useCirclesStore()
				if (!circlesStore.currentCircleMembersFullyLoaded && !this.isPublic) {
					await this.getCircleMembers(this.currentCollective.circleId)
				}
			}
		})
		subscribe('collectives:attachment:insert', this.insertAttachment)
	},

	beforeUnmount() {
		this.disposed = true
		this.unregisterCurrentSnapshotPreparer?.()
		unsubscribe('collectives:attachment:insert', this.insertAttachment)
		this.textEditWatcher()
	},

	methods: {
		t,

		...mapActions(useRootStore, ['load', 'done']),
		...mapActions(useVersionsStore, ['getVersions', 'registerCurrentSnapshotPreparer']),
		...mapActions(usePagesStore, ['setTextEdit', 'setTextPreview', 'touchPage', 'runEditorAction']),
		...mapActions(useCirclesStore, ['getCircleMembers']),

		async insertAttachment({ name }) {
			const src = '.attachments.' + this.currentPage.id + '/' + encodeAttachmentFilename(name)
			const alt = escapeHtml(name.replaceAll(/[[\]]/g, ''))
			try {
				await this.runEditorAction(async (editor, save) => {
					editor.insertAtCursor(`<img src="${src}" alt="${alt}" />`)
					if (await save() !== true) {
						throw new Error('Could not save the inserted attachment.')
					}
				})
			} catch (error) {
				console.error('Failed to insert attachment', error)
				showError(t('collectives', 'Could not insert and save the attachment. Please try again.'))
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

		async prepareCurrentEditorSnapshot() {
			if (this.isTextEdit && this.editor && await this.saveEditor() !== true) {
				throw new Error('Could not save the current page before comparison.')
			}
			return this.getVersions(this.currentPage.id)
		},

		async stopEdit() {
			if (!this.editor) {
				return
			}
			// switch back to edit if there's no content
			if (!this.pageContent?.trim()) {
				this.setTextEdit()
				this.$nextTick(() => {
					this.focusEditor()
				})
				return
			}

			try {
				if (await this.saveEditor() !== true) {
					throw new Error('The editor did not confirm saving.')
				}
				if (!this.disposed && this.currentPage.id === this.mountedPageId && this.editorContent !== this.davContent) {
					this.touchPage()
				}
			} catch {
				if (!this.disposed && this.currentPage.id === this.mountedPageId) {
					showError(t('collectives', 'Error saving the document. Please try again.'))
					this.setTextEdit()
				}
			}
		},

		async getPageContent() {
			const content = await this.fetchPageContent(this.currentPageDavUrl)
			if (!this.disposed && this.currentPage.id === this.mountedPageId) {
				this.davContent = content
				this.done('pageContent')
			}
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
