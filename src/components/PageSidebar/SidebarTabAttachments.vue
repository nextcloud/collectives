<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="attachments-container">
		<!-- loading -->
		<NcEmptyContent v-if="loading('attachments')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- error message -->
		<NcEmptyContent v-else-if="error" :name="error">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<div v-else-if="!loading('attachments') && (attachments.length || deletedAttachments.length)">
			<!-- attachments list -->
			<ul v-show="sortedAttachments.length" class="attachment-list">
				<NcListItem v-for="attachment in sortedAttachments"
					:key="attachment.id"
					:name="attachment.name"
					:href="davUrl(attachment)"
					:force-display-actions="true"
					class="attachment"
					@click="clickAttachment(attachment, $event)">
					<template #icon>
						<img lazy="true"
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
						<NcActionButton :close-after-click="true"
							@click="scrollTo(attachment)">
							<template #icon>
								<EyeIcon />
							</template>
							{{ t('collectives', 'View in document') }}
						</NcActionButton>
						<NcActionLink :href="davUrl(attachment)"
							:download="attachment.name"
							:close-after-click="true">
							<template #icon>
								<DownloadIcon />
							</template>
							{{ t('collectives', 'Download') }}
						</NcActionLink>
						<NcActionLink v-if="!isPublic"
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

			<!-- deleted attachments list -->
			<ul v-show="isTextEdit && deletedAttachments.length" class="attachment-list-deleted">
				<div class="attachment-list-subheading">
					{{ t('collectives', 'Recently deleted') }}
				</div>

				<NcListItem v-for="attachment in deletedAttachments"
					:key="attachment.id"
					:name="attachment.name"
					:href="davUrl(attachment)"
					:force-display-actions="true"
					class="attachment"
					@click="clickAttachment(attachment, $event)">
					<template #icon>
						<img lazy="true"
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
						<NcActionButton :close-after-click="true"
							@click="restore(attachment)">
							<template #icon>
								<RestoreIcon />
							</template>
							{{ t('collectives', 'Restore') }}
						</NcActionButton>
						<NcActionLink :href="davUrl(attachment)"
							:download="attachment.name"
							:close-after-click="true">
							<template #icon>
								<DownloadIcon />
							</template>
							{{ t('collectives', 'Download') }}
						</NcActionLink>
						<NcActionLink :href="filesUrl(attachment.id)"
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

		<!-- no attachments found -->
		<NcEmptyContent v-else
			:name="t('collectives', 'No attachments available')"
			:description="noAttachmentsDescription">
			<template #icon>
				<PaperclipIcon />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { usePagesStore } from '../../stores/pages.js'
import { listen } from '@nextcloud/notify_push'
import { formatFileSize } from '@nextcloud/files'
import { emit, subscribe, unsubscribe } from '@nextcloud/event-bus'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { getCurrentUser } from '@nextcloud/auth'
import { NcActionButton, NcActionLink, NcEmptyContent, NcListItem, NcLoadingIcon } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagon.vue'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import EyeIcon from 'vue-material-design-icons/Eye.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'

export default {
	name: 'SidebarTabAttachments',

	components: {
		AlertOctagonIcon,
		DownloadIcon,
		EyeIcon,
		FolderIcon,
		NcActionButton,
		NcActionLink,
		NcEmptyContent,
		NcLoadingIcon,
		NcListItem,
		PaperclipIcon,
		RestoreIcon,
	},

	props: {
		page: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			error: '',
		}
	},

	computed: {
		...mapState(useRootStore, [
			'isPublic',
			'loading',
			'shareTokenParam',
		]),
		...mapState(usePagesStore, [
			'attachments',
			'currentPage',
			'deletedAttachments',
			'pagePath',
			'pagePathTitle',
			'isTextEdit',
		]),

		noAttachmentsDescription() {
			return t('collectives', 'Add attachments using drag and drop or via "Insert attachment" in the formatting menu.')
		},

		// Sort attachments chronologically, most recent first
		sortedAttachments() {
			return [...this.attachments].sort((a, b) => a.timestamp < b.timestamp)
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

		// Encode name the same way as Text does at `insertAttachment` in MediaHandler.vue
		fileNameUriComponent() {
			return (fileName) => encodeURIComponent(fileName).replace(/[!'()*]/g, (c) => {
				return '%' + c.charCodeAt(0).toString(16).toUpperCase()
			})
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
					: OC.MimeType.getIconUrl(attachment.mimetype ?? 'undefined')
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

	watch: {
		'page.id'() {
			this.load('attachments')
			this.getAttachmentsForPage()
		},
	},

	mounted() {
		this.load('attachments')
		this.getAttachmentsForPage()
		// Reload attachment list on event from Text
		subscribe('collectives:text-image-node:add', this.getAttachmentsForPage)
		subscribe('text:image-node:add', this.getAttachmentsForPage)
		// Move attachment to recently deleted on event from Text
		subscribe('collectives:text-image-node:delete', this.onDeleteImageNode)
		subscribe('text:image-node:delete', this.onDeleteImageNode)
		// Reload attachment list on filesystem changes
		listen('notify_file', this.getAttachmentsForPage.bind(this))
	},

	beforeDestroy() {
		unsubscribe('collectives:text-image-node:add', this.getAttachmentsForPage)
		unsubscribe('text:image-node:add', this.getAttachmentsForPage)
		unsubscribe('collectives:text-image-node:delete', this.onDeleteImageNode)
		unsubscribe('text:image-node:delete', this.onDeleteImageNode)
	},

	methods: {
		...mapActions(useRootStore, ['done', 'load']),
		...mapActions(usePagesStore, [
			'getAttachments',
			'setAttachmentDeleted',
			'setAttachmentUndeleted',
		]),

		/**
		 * Get attachments for a page
		 */
		async getAttachmentsForPage() {
			try {
				await this.getAttachments(this.page)
			} catch (e) {
				this.error = t('collectives', 'Could not get attachments')
				console.error('Failed to get page attachments', e)
			} finally {
				this.done('attachments')
			}
		},

		clickAttachment(attachment, ev) {
			// Show in viewer if the mimetype is supported
			if (window.OCA.Viewer?.availableHandlers.map(handler => handler.mimes).flat().includes(attachment.mimetype)) {
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
			const element = candidates.find(el => el.dataset.src.endsWith(this.fileNameUriComponent(attachment.name)))
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
			emit('collectives:attachment:restore', this.fileNameUriComponent(attachment.name))
			this.scrollTo(attachment)
			this.setAttachmentUndeleted(attachment.name)
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
	padding: 0.3rem 8px;
	font-weight: bold;
}
</style>

<style lang="scss">
@import '../../css/animation.scss';

.highlight-animation {
	animation: highlight-animation 5s 1;
	border-radius: 8px;
}
</style>
