<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem
		:name="attachment.name"
		:href="davUrl"
		:force-display-actions="true"
		class="attachment"
		:class="{ mobile: isMobile }"
		@dragstart="onDragstart"
		@click="onClick">
		<template #icon>
			<img
				lazy="true"
				:src="previewUrl"
				alt=""
				height="256"
				width="256"
				class="attachment__image">
		</template>
		<template #subname>
			<div class="attachment__info">
				<span class="attachment__info_size">{{ formattedFilesize }}</span>
				<span class="attachment__info_size">·</span>
				<span :title="formattedDate">{{ relativeDate }}</span>
			</div>
		</template>
		<template #actions>
			<NcActionButton
				v-if="isEmbedded"
				:close-after-click="true"
				@click="scrollTo()">
				<template #icon>
					<EyeIcon />
				</template>
				{{ t('collectives', 'View in page') }}
			</NcActionButton>
			<NcActionButton
				v-if="!isEmbedded && !isDeleted && !isInFolder && editorApiAttachments && isTextEdit && currentCollectiveCanEdit"
				:close-after-click="true"
				@click="onInsert">
				<template #icon>
					<FileDocumentPlusOutlineIcon />
				</template>
				{{ t('collectives', 'Add to page') }}
			</NcActionButton>
			<NcActionButton
				v-if="isDeleted && currentCollectiveCanEdit"
				:close-after-click="true"
				@click="$emit('restore')">
				<template #icon>
					<RestoreIcon />
				</template>
				{{ t('collectives', 'Restore') }}
			</NcActionButton>
			<NcActionLink
				:v-if="!isDeleted"
				:href="davUrl"
				:download="attachment.name"
				:class="{ 'action-link--disabled': !networkOnline }"
				:close-after-click="true">
				<template #icon>
					<DownloadIcon />
				</template>
				{{ t('collectives', 'Download') }}
			</NcActionLink>
			<NcActionLink
				v-if="!isDeleted && !isPublic"
				:href="filesUrl"
				:class="{ 'action-link--disabled': !networkOnline }"
				:close-after-click="true">
				<template #icon>
					<FolderIcon />
				</template>
				{{ t('collectives', 'Show in Files') }}
			</NcActionLink>
			<NcActionButton
				v-if="!isDeleted && !isInFolder && currentCollectiveCanEdit"
				:close-after-click="true"
				:class="{ 'action-link--disabled': !networkOnline }"
				@click="$emit('rename')">
				<template #icon>
					<PencilOutlineIcon />
				</template>
				{{ t('collectives', 'Rename') }}
			</NcActionButton>
			<NcActionButton
				v-if="!isDeleted && !isInFolder && currentCollectiveCanEdit"
				:close-after-click="true"
				:class="{ 'action-link--disabled': !networkOnline }"
				@click="$emit('delete')">
				<template #icon>
					<DeleteIcon />
				</template>
				{{ t('collectives', 'Delete') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import { emit } from '@nextcloud/event-bus'
import { formatFileSize } from '@nextcloud/files'
import { t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'
import { generateRemoteUrl, generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActionLink, NcListItem } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapState } from 'pinia'
import DeleteIcon from 'vue-material-design-icons/DeleteOutline.vue'
import EyeIcon from 'vue-material-design-icons/EyeOutline.vue'
import FileDocumentPlusOutlineIcon from 'vue-material-design-icons/FileDocumentPlusOutline.vue'
import FolderIcon from 'vue-material-design-icons/FolderOutline.vue'
import PencilOutlineIcon from 'vue-material-design-icons/PencilOutline.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { editorApiAttachments } from '../../constants.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { encodeAttachmentFilename } from '../../util/attachmentFilename.ts'

export default {
	name: 'AttachmentItem',

	components: {
		DeleteIcon,
		EyeIcon,
		FileDocumentPlusOutlineIcon,
		FolderIcon,
		DownloadIcon,
		NcActionButton,
		NcActionLink,
		NcListItem,
		PencilOutlineIcon,
		RestoreIcon,
	},

	props: {
		attachment: {
			type: Object,
			required: true,
		},

		isEmbedded: {
			type: Boolean,
			default: false,
		},

		isDeleted: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		const { networkOnline } = useNetworkState()
		return { isMobile, networkOnline }
	},

	computed: {
		...mapState(useRootStore, [
			'editorApiFlags',
			'isPublic',
			'shareTokenParam',
		]),

		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),

		...mapState(usePagesStore, [
			'currentPage',
			'isTextEdit',
		]),

		editorApiAttachments() {
			return this.editorApiFlags.includes(editorApiAttachments)
		},

		isInFolder() {
			return this.attachment.type === 'folder'
		},

		attachmentPreview() {
			const searchParams = '&x=64&y=64&a=true'
			return this.isPublic
				? generateUrl(`/apps/files_sharing/publicpreview/${this.shareTokenParam}?fileId=${this.attachment.id}&file=${encodeURI(this.attachment.internalPath)}${searchParams}`)
				: generateUrl(`/core/preview?fileId=${this.attachment.id}${searchParams}`)
		},

		previewUrl() {
			return this.attachment.hasPreview
				? this.attachmentPreview
				: OC.MimeType.getIconUrl(this.attachment.mimetype ?? 'undefined')
		},

		filesUrl() {
			return generateUrl(`/f/${this.attachment.id}`)
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
			const [path, filename] = this.pathSplit(this.attachment.internalPath)
			return this.isPublic
				? generateUrl(`s/${this.shareTokenParam}/download?path=/${path}&files=${filename}`)
				: generateRemoteUrl(`dav/files/${getCurrentUser()?.uid}/${this.currentPage.collectivePath}/${encodeURI(this.attachment.internalPath)}`)
		},

		formattedFilesize() {
			return formatFileSize(this.attachment.filesize)
		},

		formattedDate() {
			return moment.unix(this.attachment.timestamp).format('LLL')
		},

		relativeDate() {
			return moment.unix(this.attachment.timestamp).fromNow()
		},
	},

	methods: {
		t,

		getActiveTextElement() {
			return this.isTextEdit
				? document.querySelector('[data-collectives-el="editor"]')
				: document.querySelector('[data-collectives-el="reader"]')
		},

		scrollTo() {
			const candidates = [...this.getActiveTextElement().querySelectorAll('[data-component="image-view"]')]
			const element = candidates.find((el) => el.dataset.src.endsWith(encodeAttachmentFilename(this.attachment.name)))
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

		onDragstart(event) {
			if (this.isInFolder) {
				return
			}

			const url = this.davUrl
			const html = `<img src="${this.attachment.src}" alt="${this.attachment.name}">`

			event.dataTransfer.effectAllowed = 'link'
			event.dataTransfer.setData('text/plain', url)
			event.dataTransfer.setData('text/uri-list', url)
			event.dataTransfer.setData('text/html', html)
		},

		onClick(event) {
			// Show in viewer if the mimetype is supported
			if (window.OCA.Viewer?.availableHandlers.map((handler) => handler.mimes).flat().includes(this.attachment.mimetype)) {
				event.preventDefault()
				window.OCA.Viewer.open({ path: this.attachment.path })
			}
		},

		onInsert() {
			emit('collectives:attachment:insert', {
				name: this.attachment.name,
			})
			this.scrollTo(this.attachment)
		},
	},
}
</script>

<style lang="scss" scoped>
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

	&.mobile, &:hover, &:focus, &:active {
		:deep(.list-item-content__actions) {
			visibility: visible;
		}
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

:deep(.list-item-content__actions) {
	visibility: hidden;
}
</style>
