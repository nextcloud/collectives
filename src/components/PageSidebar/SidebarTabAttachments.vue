<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		ref="attachmentsContainer"
		class="attachments-container"
		@dragover="onDragover"
		@dragleave="onDragleave"
		@drop="onDrop">
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

		<div v-else-if="!loading('attachments')">
			<!-- upload button and area -->
			<div v-if="currentCollectiveCanEdit" class="upload-button">
				<NcButton @click="$refs.fileInput.click()">
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
				<div v-show="dragover" class="upload-drop-area">
					<TrayArrowDownIcon :size="24" />
					<div class="upload-drop-area__title">
						{{ t('collectives', 'Drag and drop files here to upload') }}
					</div>
				</div>
			</div>

			<!-- text attachments list -->
			<div v-if="textAttachments.length">
				<ul class="attachment-list">
					<NcListItem
						v-for="attachment in textAttachments"
						:key="attachment.id"
						:name="attachment.name"
						:href="davUrl(attachment)"
						:force-display-actions="true"
						class="attachment"
						@click="clickAttachment(attachment, $event)">
						<template #icon>
							<img
								lazy="true"
								:src="previewUrl(attachment)"
								alt=""
								height="256"
								width="256"
								class="attachment__image">
						</template>
						<template #subname>
							<div class="attachment__info">
								<span class="attachment__info_size">{{ formattedFileSize(attachment.filesize) }}</span>
								<span class="attachment__info_size">·</span>
								<span :title="formattedDate(attachment.timestamp)">{{ relativeDate(attachment.timestamp) }}</span>
							</div>
						</template>
						<template #actions>
							<NcActionButton
								:close-after-click="true"
								@click="scrollTo(attachment)">
								<template #icon>
									<EyeIcon />
								</template>
								{{ t('collectives', 'View in document') }}
							</NcActionButton>
							<NcActionLink
								:href="davUrl(attachment)"
								:download="attachment.name"
								:class="{ 'action-link--disabled': !networkOnline }"
								:title="offlineTitle"
								:close-after-click="true">
								<template #icon>
									<DownloadIcon />
								</template>
								{{ t('collectives', 'Download') }}
							</NcActionLink>
							<NcActionLink
								v-if="!isPublic"
								:href="filesUrl(attachment.id)"
								:class="{ 'action-link--disabled': !networkOnline }"
								:title="offlineTitle"
								:close-after-click="true">
								<template #icon>
									<FolderIcon />
								</template>
								{{ t('collectives', 'Show in Files') }}
							</NcActionLink>
							<NcActionButton
								v-if="currentCollectiveCanEdit"
								:close-after-click="true"
								:class="{ 'action-link--disabled': !networkOnline }"
								@click="onStartRename(attachment)">
								<template #icon>
									<PencilOutlineIcon />
								</template>
								{{ t('collectives', 'Rename') }}
							</NcActionButton>
							<NcActionButton
								v-if="currentCollectiveCanEdit"
								:close-after-click="true"
								:class="{ 'action-link--disabled': !networkOnline }"
								@click="onDelete(attachment)">
								<template #icon>
									<DeleteIcon />
								</template>
								{{ t('collectives', 'Delete') }}
							</NcActionButton>
						</template>
					</NcListItem>
				</ul>
			</div>

			<!-- deleted attachments list -->
			<div v-if="isTextEdit && deletedAttachments.length">
				<div class="attachment-list-subheading">
					{{ t('collectives', 'Recently deleted') }}
				</div>

				<ul class="attachment-list-deleted">
					<NcListItem
						v-for="attachment in deletedAttachments"
						:key="attachment.id"
						:name="attachment.name"
						:href="davUrl(attachment)"
						:force-display-actions="true"
						class="attachment"
						@click="clickAttachment(attachment, $event)">
						<template #icon>
							<img
								lazy="true"
								:src="previewUrl(attachment)"
								alt=""
								height="256"
								width="256"
								class="attachment__image">
						</template>
						<template #subname>
							<div class="attachment__info">
								<span class="attachment__info_size">{{ formattedFileSize(attachment.filesize) }}</span>
								<span class="attachment__info_size">·</span>
								<span :title="formattedDate(attachment.timestamp)">{{ relativeDate(attachment.timestamp) }}</span>
							</div>
						</template>
						<template #actions>
							<NcActionButton
								:close-after-click="true"
								@click="restore(attachment)">
								<template #icon>
									<RestoreIcon />
								</template>
								{{ t('collectives', 'Restore') }}
							</NcActionButton>
							<NcActionLink
								:href="davUrl(attachment)"
								:download="attachment.name"
								:close-after-click="true">
								<template #icon>
									<DownloadIcon />
								</template>
								{{ t('collectives', 'Download') }}
							</NcActionLink>
							<NcActionLink
								:href="filesUrl(attachment.id)"
								:close-after-click="true">
								<template #icon>
									<FolderIcon />
								</template>
								{{ t('collectives', 'Show in Files') }}
							</NcActionLink>
						</template>
					</NcListItem>
				</ul>
			</div>

			<!-- folder attachments list -->
			<div v-if="folderAttachments.length">
				<div class="attachment-list-subheading">
					<div>
						{{ t('collectives', 'Files next to page') }}
					</div>
					<NcPopover popover-role="dialog" no-focus-trap>
						<template #trigger>
							<NcButton
								class="hint-icon"
								variant="tertiary-no-background"
								:aria-label="t('collectives', 'Files next to page explanation')">
								<template #icon>
									<InformationIcon :size="20" />
								</template>
							</NcButton>
						</template>
						<p class="hint-body">
							{{ t('collectives', 'Files that are stored in the same folder as the page.') }}
						</p>
					</NcPopover>
				</div>

				<ul class="attachments-list">
					<NcListItem
						v-for="attachment in folderAttachments"
						:key="attachment.id"
						:name="attachment.name"
						:href="davUrl(attachment)"
						:force-display-actions="true"
						class="attachment"
						@click="clickAttachment(attachment, $event)">
						<template #icon>
							<img
								lazy="true"
								:src="previewUrl(attachment)"
								alt=""
								height="256"
								width="256"
								class="attachment__image">
						</template>
						<template #subname>
							<div class="attachment__info">
								<span class="attachment__info_size">{{ formattedFileSize(attachment.filesize) }}</span>
								<span class="attachment__info_size">·</span>
								<span :title="formattedDate(attachment.timestamp)">{{ relativeDate(attachment.timestamp) }}</span>
							</div>
						</template>
						<template #actions>
							<NcActionLink
								:href="davUrl(attachment)"
								:download="attachment.name"
								:class="{ 'action-link--disabled': !networkOnline }"
								:title="offlineTitle"
								:close-after-click="true">
								<template #icon>
									<DownloadIcon />
								</template>
								{{ t('collectives', 'Download') }}
							</NcActionLink>
							<NcActionLink
								v-if="!isPublic"
								:href="filesUrl(attachment.id)"
								:class="{ 'action-link--disabled': !networkOnline }"
								:title="offlineTitle"
								:close-after-click="true">
								<template #icon>
									<FolderIcon />
								</template>
								{{ t('collectives', 'Show in Files') }}
							</NcActionLink>
						</template>
					</NcListItem>
				</ul>
			</div>
		</div>

		<!-- no attachments found -->
		<NcEmptyContent
			v-else-if="!attachments.length && !folderAttachments.length"
			:name="t('collectives', 'No attachments available')"
			:description="noAttachmentsDescription">
			<template #icon>
				<PaperclipIcon />
			</template>
		</NcEmptyContent>

		<!-- rename dialog -->
		<AttachmentRenameDialog
			v-if="renamedAttachment"
			:open.sync="showRenameAttachmentsForm"
			:attachment-name="renamedAttachment.name"
			@attachment-rename="onRename" />
	</div>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { mapActions, mapState } from 'pinia'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagonOutline.vue'
import DeleteIcon from 'vue-material-design-icons/DeleteOutline.vue'
import EyeIcon from 'vue-material-design-icons/EyeOutline.vue'
import FolderIcon from 'vue-material-design-icons/FolderOutline.vue'
import InformationIcon from 'vue-material-design-icons/InformationOutline.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import PlusIcon from 'vue-material-design-icons/Plus.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import TrayArrowDownIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import AttachmentRenameDialog from './AttachmentRenameDialog.vue'
import OfflineContent from './OfflineContent.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { encodeAttachmentFilename } from '../../util/attachmentFilename.ts'

export default {
	name: 'SidebarTabAttachments',

	components: {
		AlertOctagonIcon,
		AttachmentRenameDialog,
		DeleteIcon,
		DownloadIcon,
		EyeIcon,
		FolderIcon,
		InformationIcon,
		NcActionButton,
		NcActionLink,
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		NcListItem,
		NcPopover,
		OfflineContent,
		PaperclipIcon,
		PencilOutlineIcon,
		PlusIcon,
		RestoreIcon,
		TrayArrowDownIcon,
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	data() {
		return {
			dragover: false,
			renamedAttachment: null,
			showRenameAttachmentsForm: false,
		}
	},

	computed: {
		...mapState(useRootStore, [
			'isPublic',
			'loading',
			'shareTokenParam',
		]),

		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),

		...mapState(usePagesStore, [
			'attachments',
			'attachmentsError',
			'attachmentsLoaded',
			'currentPage',
			'deletedAttachments',
			'isTextEdit',
		]),

		noAttachmentsDescription() {
			return t('collectives', 'Add attachments using drag and drop or via "Insert attachment" in the formatting menu.')
		},

		textAttachments() {
			return [...this.attachments]
				.filter((a) => a.type === 'text')
				// Sort attachments chronologically, most recent first
				.sort((a, b) => a.timestamp < b.timestamp)
		},

		folderAttachments() {
			return [...this.attachments]
				.filter((a) => a.type === 'folder')
				// Sort attachments chronologically, most recent first
				.sort((a, b) => a.timestamp < b.timestamp)
		},

		offlineTitle() {
			return this.networkOnline
				? ''
				: t('collectives', 'You are offline')
		},

		formattedFileSize() {
			return (fileSize) => formatFileSize(fileSize)
		},

		formattedDate() {
			return (timestamp) => moment.unix(timestamp).format('LLL')
		},

		relativeDate() {
			return (timestamp) => moment.unix(timestamp).fromNow()
		},

		filesUrl() {
			return (fileId) => generateUrl(`/f/${fileId}`)
		},

		pathSplit() {
			return (filepath) => {
				const lastSlashIndex = filepath.lastIndexOf('/')
				const path = filepath.substring(0, lastSlashIndex)
				const filename = filepath.substring(lastSlashIndex + 1)
				return [path, filename]
			}
		},

		davUrl() {
			return function(attachment) {
				const [path, filename] = this.pathSplit(attachment.internalPath)
				return this.isPublic
					? generateUrl(`s/${this.shareTokenParam}/download?path=/${path}&files=${filename}`)
					: generateRemoteUrl(`dav/files/${getCurrentUser()?.uid}/${this.currentPage.collectivePath}/${encodeURI(attachment.internalPath)}`)
			}
		},

		previewUrl() {
			return function(attachment) {
				return attachment.hasPreview
					? this.attachmentPreview(attachment)
					: window.OC.MimeType.getIconUrl(attachment.mimetype ?? 'undefined')
			}
		},

		attachmentPreview() {
			return function(attachment) {
				const searchParams = '&x=64&y=64&a=true'
				return this.isPublic
					? generateUrl(`/apps/files_sharing/publicpreview/${this.shareTokenParam}?fileId=${attachment.id}&file=${encodeURI(attachment.internalPath)}${searchParams}`)
					: generateUrl(`/core/preview?fileId=${attachment.id}${searchParams}`)
			}
		},
	},

	mounted() {
		// Move attachment to recently deleted on event from Text
		subscribe('collectives:text-image-node:delete', this.onDeleteImageNode)
		subscribe('text:image-node:delete', this.onDeleteImageNode)
	},

	beforeDestroy() {
		unsubscribe('collectives:text-image-node:delete', this.onDeleteImageNode)
		unsubscribe('text:image-node:delete', this.onDeleteImageNode)
	},

	methods: {
		t,

		...mapActions(usePagesStore, [
			'deleteAttachment',
			'renameAttachment',
			'setAttachmentDeleted',
			'setAttachmentUndeleted',
			'uploadAttachment',
		]),

		onDragover(event) {
			// Needed to keep the drag/drop chain working
			event.preventDefault()

			const isForeignFile = event.dataTransfer?.types.includes('Files')
			if (isForeignFile) {
				this.dragover = true
				this.$refs.attachmentsContainer.scrollIntoView({ behaviour: 'smooth', block: 'start' })
			}
		},

		onDragleave() {
			if (this.dragover) {
				this.dragover = false
			}
		},

		onDrop(event) {
			const files = event.dataTransfer?.files
			if (!files) {
				return
			}

			event.preventDefault()
			event.stopPropagation()

			this.uploadAttachments(files)
			this.dragover = false
		},

		clickAttachment(attachment, ev) {
			// Show in viewer if the mimetype is supported
			if (window.OCA.Viewer?.availableHandlers.map((handler) => handler.mimes).flat().includes(attachment.mimetype)) {
				ev.preventDefault()
				window.OCA.Viewer.open({ path: attachment.path })
			}
		},

		getActiveTextElement() {
			return this.isTextEdit
				? document.querySelector('[data-collectives-el="editor"]')
				: document.querySelector('[data-collectives-el="reader"]')
		},

		scrollTo(attachment) {
			const candidates = [...this.getActiveTextElement().querySelectorAll('[data-component="image-view"]')]
			const element = candidates.find((el) => el.dataset.src.endsWith(encodeAttachmentFilename(attachment.name)))
			if (element) {
				// Scroll into view
				element.scrollIntoView({ block: 'center' })
				// Highlight
				element.children[0].classList.add('highlight-animation')
				setTimeout(() => {
					element.children[0].classList.remove('highlight-animation')
				}, 5000)
			}
		},

		restore(attachment) {
			emit('collectives:attachment:restore', {
				name: attachment.name,
			})
			this.scrollTo(attachment)
			this.setAttachmentUndeleted(attachment.name)
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
					this.$refs.fileInput.value = ''
				} catch (e) {
					console.error('Failed to upload attachment', e)
					showError(t('collectives', 'Failed to upload attachment {name}', { name: file.name }))
				}
			}
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
				console.error('Failed to rename folder attachment', e)
				showError(t('collectives', 'Failed to rename folder attachment', {}))
			}
		},

		async onDelete(attachment) {
			try {
				await this.deleteAttachment(attachment.id)
				emit('collectives:attachment:removeReferences', {
					pageId: this.currentPage.id,
					name: attachment.name,
				})
				showSuccess(t('collectives', 'Deleted folder attachment {name}', { name: attachment.name }))
			} catch (e) {
				console.error('Failed to delete folder attachment', e)
				showError(t('collectives', 'Failed to delete folder attachment'))
			}
		},

		onDeleteImageNode(imageUrl) {
			const url = new URL(imageUrl, window.location)
			const imageFileName = url.searchParams.get('imageFileName') || url.searchParams.get('mediaFileName')
			if (!url.pathname.includes('/apps/text/') || !imageFileName) {
				// Ignore image nodes that don't point to direct Text attachments
				return
			}
			this.setAttachmentDeleted(imageFileName)
		},
	},
}
</script>

<style lang="scss" scoped>
.attachments-container {
	height: calc(100% - 24px);
}

.upload-button {
	padding-block: 4px;
	padding-inline: 8px;
}

.upload-drop-area {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 100%;
	user-select: none;
	color: var(--color-text-maxcontrast);
	background-color: var(--color-main-background);
	margin-block: 8px;
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

	.hint-body {
		max-width: 300px;
		padding: var(--border-radius-element);
	}
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
