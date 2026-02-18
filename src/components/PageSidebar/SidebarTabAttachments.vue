<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		ref="attachmentsContainer"
		class="attachments-container"
		@dragover="onContainerDragover"
		@dragleave="onContainerDragleave"
		@drop="onContainerDrop">
		<!-- loading -->
		<NcEmptyContent v-if="loading('attachments')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- offline -->
		<OfflineContent v-else-if="!attachmentsLoaded && !networkOnline" />

		<!-- error message -->
		<NcEmptyContent v-else-if="attachmentsError" :name="t('collectives', 'Could not get attachments')">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<div v-else>
			<!-- upload button and area -->
			<div v-if="currentCollectiveCanEdit" v-show="networkOnline" class="upload-area">
				<NcButton :disabled="!networkOnline" variant="tertiary" @click="$refs.fileInput.click()">
					<template #icon>
						<PlusIcon />
					</template>
					{{ t('collectives', 'Upload') }}
				</NcButton>

				<input
					ref="fileInput"
					type="file"
					multiple
					style="display: none"
					@change="onFilesSelected">

				<!-- drag and drop notice -->
				<div v-show="isDragover" class="upload-drop-area">
					<TrayArrowDownIcon :size="24" />
					<div class="upload-drop-area__title">
						{{ t('collectives', 'Drag and drop files here to upload') }}
					</div>
				</div>
			</div>

			<!-- attachments lists -->
			<div v-if="hasAttachments">
				<!-- text attachments in page list -->
				<div v-if="embeddedAttachments.length">
					<div class="attachment-list-subheading">
						<div>
							{{ t('collectives', 'In page') }}
						</div>
					</div>

					<ul class="attachment-list-embedded">
						<AttachmentItem
							v-for="attachment in embeddedAttachments"
							:key="attachment.id"
							:attachment="attachment"
							:is-embedded="true"
							@rename="onStartRename(attachment)"
							@delete="onDelete(attachment)" />
					</ul>
				</div>

				<!-- text attachments not in page list -->
				<div v-if="notEmbeddedAttachments.length">
					<div v-if="editorApiAttachments" class="attachment-list-subheading">
						<div>
							{{ t('collectives', 'Not in page') }}
						</div>
					</div>

					<ul class="attachment-list-not-embedded">
						<AttachmentItem
							v-for="attachment in notEmbeddedAttachments"
							:key="attachment.id"
							:attachment="attachment"
							@rename="onStartRename(attachment)"
							@delete="onDelete(attachment)" />
					</ul>
				</div>

				<!-- deleted attachments list -->
				<div v-if="deletedAttachments.length">
					<div class="attachment-list-subheading">
						{{ t('collectives', 'Recently deleted') }}
					</div>

					<ul class="attachment-list-deleted">
						<AttachmentItem
							v-for="attachment in deletedAttachments"
							:key="attachment.id"
							:attachment="attachment"
							:is-deleted="true"
							@restore="onRestore(attachment)" />
					</ul>
				</div>

				<!-- folder attachments list -->
				<div v-if="folderAttachments.length">
					<div class="attachment-list-subheading">
						<div>
							{{ t('collectives', 'Found in folder') }}
						</div>
						<NcPopover popover-role="dialog" no-focus-trap>
							<template #trigger>
								<NcButton
									class="hint-icon"
									variant="tertiary-no-background"
									:aria-label="t('collectives', 'Explanation for found in folder list')">
									<template #icon>
										<InformationIcon :size="20" />
									</template>
								</NcButton>
							</template>
							<p class="hint-body">
								{{ t('collectives', 'Files in the folder of the page.') }}
							</p>
						</NcPopover>
					</div>

					<ul class="attachment-list-folder">
						<AttachmentItem
							v-for="attachment in folderAttachments"
							:key="attachment.id"
							:attachment="attachment" />
					</ul>
				</div>
			</div>

			<!-- no attachments found -->
			<NcEmptyContent
				v-else
				:name="t('collectives', 'No attachments')"
				:description="noAttachmentsDescription">
				<template #icon>
					<PaperclipIcon />
				</template>
			</NcEmptyContent>
		</div>

		<!-- rename dialog -->
		<AttachmentRenameDialog
			v-if="renamedAttachment"
			v-model:open="showRenameAttachmentsForm"
			:attachment-name="renamedAttachment.name"
			@attachment-rename="onRename" />
	</div>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagonOutline.vue'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import TrayArrowDownIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import AttachmentItem from './AttachmentItem.vue'
import AttachmentRenameDialog from './AttachmentRenameDialog.vue'
import OfflineContent from './OfflineContent.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { editorApiAttachments } from '../../constants.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'SidebarTabAttachments',

	components: {
		AlertOctagonIcon,
		AttachmentItem,
		AttachmentRenameDialog,
		InformationIcon,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcPopover,
		OfflineContent,
		PaperclipIcon,
		PlusIcon,
		TrayArrowDownIcon,
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	data() {
		return {
			isDragover: false,
			renamedAttachment: null,
			showRenameAttachmentsForm: false,
		}
	},

	computed: {
		...mapState(useRootStore, ['editorApiFlags', 'loading']),

		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),

		...mapState(usePagesStore, [
			'attachments',
			'attachmentsError',
			'attachmentsLoaded',
			'editorEmbeddedAttachmentSrcs',
			'readerEmbeddedAttachmentSrcs',
			'currentPage',
			'deletedAttachments',
		]),

		editorApiAttachments() {
			return this.editorApiFlags.includes(editorApiAttachments)
		},

		noAttachmentsDescription() {
			return t('collectives', 'Add attachments using drag and drop or via "Insert attachment" in the formatting menu.')
		},

		embeddedAttachmentSrcs() {
			return this.currentCollectiveCanEdit
				? this.editorEmbeddedAttachmentSrcs
				: this.readerEmbeddedAttachmentSrcs
		},

		textAttachments() {
			return [...this.attachments]
				.filter((a) => a.type === 'text')
				// Sort attachments chronologically, most recent first
				.sort((a, b) => a.timestamp < b.timestamp)
		},

		embeddedAttachments() {
			return this.textAttachments.filter((attachment) => this.embeddedAttachmentSrcs.includes(attachment.src))
		},

		notEmbeddedAttachments() {
			return this.textAttachments.filter((attachment) => !this.embeddedAttachmentSrcs.includes(attachment.src))
		},

		folderAttachments() {
			return [...this.attachments]
				.filter((a) => a.type === 'folder')
				// Sort attachments chronologically, most recent first
				.sort((a, b) => a.timestamp < b.timestamp)
		},

		hasAttachments() {
			return this.embeddedAttachments.length > 0
				|| this.notEmbeddedAttachments.length > 0
				|| this.deletedAttachments.length > 0
				|| this.folderAttachments.length > 0
		},

		offlineTitle() {
			return this.networkOnline
				? ''
				: t('collectives', 'You are offline')
		},
	},

	methods: {
		t,

		...mapActions(usePagesStore, [
			'deleteAttachment',
			'renameAttachment',
			'restoreAttachment',
			'uploadAttachment',
		]),

		onContainerDragover(event) {
			// Needed to keep the drag/drop chain working
			event.preventDefault()

			const isForeignFile = event.dataTransfer?.types.includes('Files')
			if (isForeignFile) {
				this.isDragover = true
				this.$refs.attachmentsContainer.scrollIntoView({ behaviour: 'smooth', block: 'start' })
			}
		},

		onContainerDragleave() {
			if (this.isDragover) {
				this.isDragover = false
			}
		},

		onContainerDrop(event) {
			const files = event.dataTransfer?.files
			if (!files) {
				return
			}

			event.preventDefault()
			event.stopPropagation()

			this.uploadAttachments(files)
			this.isDragover = false
		},

		async onFilesSelected(event) {
			const files = event.target?.files
			if (!files) {
				return
			}

			await this.uploadAttachments(files)
		},

		async uploadAttachments(files) {
			for (const file of files) {
				try {
					await this.uploadAttachment(file)
				} catch (e) {
					console.error('Failed to upload attachment', e)
					showError(t('collectives', 'Failed to upload attachment {name}', { name: file.name }))
				}
			}
			this.$refs.fileInput.value = ''
		},

		async onStartRename(attachment) {
			this.showRenameAttachmentsForm = true
			this.renamedAttachment = attachment
		},

		async onRename(newName) {
			const oldName = this.renamedAttachment.name
			this.renamedAttachment.name = newName
			this.showRenameAttachmentsForm = false

			try {
				const newAttachment = await this.renameAttachment(this.renamedAttachment.id, newName)
				this.renamedAttachment = null
				emit('collectives:attachment:replaceFilename', {
					pageId: this.currentPage.id,
					oldName,
					newName: newAttachment.name,
				})
			} catch (e) {
				this.renamedAttachment.name = oldName
				console.error('Failed to rename attachment', e)
				showError(t('collectives', 'Failed to rename attachment', {}))
			}
		},

		async onDelete(attachment) {
			try {
				await this.deleteAttachment(attachment.id)
				emit('collectives:attachment:removeReferences', {
					pageId: this.currentPage.id,
					name: attachment.name,
				})
				showSuccess(t('collectives', 'Deleted attachment {name}', { name: attachment.name }))
			} catch (e) {
				console.error('Failed to delete attachment', e)
				showError(t('collectives', 'Failed to delete attachment'))
			}
		},

		async onRestore(attachment) {
			console.debug('onRestore', attachment)
			try {
				await this.restoreAttachment(attachment.id)
			} catch (e) {
				console.error('Failed to restore attachment', e)
				showError(t('collectives', 'Failed to restore attachment'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.attachments-container {
	height: calc(100% - 24px);
}

.upload-area {
	padding-block: 8px;
}

.upload-drop-area {
	display: flex;
	align-items: center;
	justify-content: center;
	user-select: none;
	color: var(--color-text-maxcontrast);
	background-color: var(--color-main-background);
	margin: 8px;
	padding: 12px;
	border: 2px var(--color-border-dark) dashed;
	border-radius: var(--border-radius-large);

	&__title {
		font-weight: bold;
		font-size: 1.2em;
		padding-inline-start: 12px;
		color: inherit;
	}
}

.attachment {
	display: flex;
	flex-direction: row;

	:deep(.line-one__name) {
		font-weight: normal;
	}

	&__info {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 0.5rem;

		&__size {
			color: var(--color-text-lighter);
		}
	}

	&__image {
		width: 3rem;
		height: 3rem;
		border: 1px solid var(--color-border);
		border-radius: var(--border-radius-large);
	}
}

.attachment-list-subheading {
	display: flex;
	align-items: center;
	padding: 0.3rem 8px;
	font-weight: bold;

	.hint-icon {
		color: var(--color-primary-element);
	}
}

.hint-body {
	max-width: 300px;
	padding: var(--border-radius-element);
}

.action-link--disabled {
	pointer-events: none;
	opacity: .5;
}
</style>

<style lang="scss">
@use '../../css/animation';

.highlight-animation {
	animation: highlight-animation 5s 1;
	border-radius: 8px;
}
</style>
