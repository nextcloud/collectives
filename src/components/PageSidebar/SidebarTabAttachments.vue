<template>
	<div class="attachments-container">
		<!-- loading -->
		<NcEmptyContent v-if="loading('attachments')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- error message -->
		<NcEmptyContent v-else-if="error" :title="error">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<!-- backlinks list -->
		<ul v-else-if="!loading('attachments') && attachments.length" class="attachment-list">
			<li v-for="attachment in sortedAttachments"
				:key="attachment.id"
				class="attachment">
				<a class="fileicon"
					:href="filesUrl(attachment.id)"
					:style="mimetypeForAttachment(attachment)"
					@click.prevent="showViewer(attachment)" />
				<div class="details">
					<a :href="filesUrl(attachment.id)" @click.prevent="showViewer(attachment)">
						<div class="filename">
							<span class="basename">{{ attachment.name }}</span>
						</div>
						<div>
							<span class="filesize">{{ formattedFileSize(attachment.filesize) }}</span>
							<span class="filedate">{{ relativeDate(attachment.timestamp) }}</span>
						</div>
					</a>
				</div>
				<NcActions :force-menu="true">
					<NcActionLink icon="icon-folder"
						:href="filesUrl(attachment.id)"
						:close-after-click="true">
						{{ t('collectives', 'Show in Files') }}
					</NcActionLink>
				</NcActions>
			</li>
		</ul>

		<!-- no attachments found -->
		<NcEmptyContent v-else
			:title="t('collectives', 'No attachments available')"
			:description="t('collectives', 'If the page has attachments, they will be listed here.')">
			<template #icon>
				<PaperclipIcon />
			</template>
		</NcEmptyContent>

		<div class="attachments-infobox">
			<InformationIcon />
			<div class="content">
				{{ t('collectives', 'Add media to the document using drag & drop or via "Insert attachment"') }}
			</div>
		</div>
	</div>
</template>

<script>
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { GET_ATTACHMENTS } from '../../store/actions.js'
import { listen } from '@nextcloud/notify_push'
import { formatFileSize } from '@nextcloud/files'
import { NcActions, NcActionLink, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import moment from '@nextcloud/moment'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagon.vue'
import InformationIcon from 'vue-material-design-icons/Information.vue'
import PaperclipIcon from 'vue-material-design-icons/Paperclip.vue'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'SidebarTabAttachments',

	components: {
		AlertOctagonIcon,
		InformationIcon,
		NcActions,
		NcActionLink,
		NcEmptyContent,
		NcLoadingIcon,
		PaperclipIcon,
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
		...mapState({
			attachments: (state) => state.pages.attachments,
		}),
		...mapGetters([
			'loading',
			'pagePath',
			'pagePathTitle',
		]),

		// Sort attachments chronologically, most recent first
		sortedAttachments() {
			return [...this.attachments].sort((a, b) => a.timestamp < b.timestamp)
		},

		relativeDate() {
			return (timestamp) => moment.unix(timestamp).fromNow()
		},

		filesUrl() {
			return (fileId) => generateUrl(`/f/${fileId}`)
		},

		formattedFileSize() {
			return (fileSize) => formatFileSize(fileSize)
		},

		mimetypeForAttachment() {
			return (attachment) => {
				if (!attachment) {
					return {}
				}
				const url = attachment.hasPreview
					? this.attachmentPreview(attachment)
					: OC.MimeType.getIconUrl(attachment.mimetype ?? 'undefined')
				return {
					'background-image': `url("${url}")`,
				}
			}
		},

		attachmentPreview() {
			return (attachment) => (attachment.id ? generateUrl(`/core/preview?fileId=${attachment.id}&x=64&y=64&a=true`) : null)
		},
	},

	watch: {
		'page.id'() {
			this.load('attachments')
			this.unsetAttachments()
			this.getAttachments()
		},
	},

	mounted() {
		this.load('attachments')
		this.getAttachments()
		listen('notify_file', this.getAttachments.bind(this))
	},

	methods: {
		...mapMutations(['done', 'load', 'unsetAttachments']),

		...mapActions({
			dispatchGetAttachments: GET_ATTACHMENTS,
		}),

		/**
		 * Get attachments for a page
		 */
		async getAttachments() {
			try {
				this.done('attachments')
				this.dispatchGetAttachments(this.page)
			} catch (e) {
				this.error = t('collectives', 'Could not get attachments')
				console.error('Failed to get page attachments', e)
			}
		},

		showViewer(attachment) {
			if (window.OCA.Viewer.availableHandlers.map(handler => handler.mimes).flat().includes(attachment.mimetype)) {
				window.OCA.Viewer.open({ path: attachment.path })
				return
			}

			window.location = this.filesUrl(attachment.id)
		},
	},
}
</script>

<style lang="scss" scoped>
.attachments-container {
	height: calc(100% - 24px);
}

li.attachment {
	display: flex;
	padding: 3px;
	min-height: 44px;

	&:hover, &:focus, &:active {
		background-color: var(--color-background-hover);
	}

	.fileicon {
		display: inline-block;
		min-width: 32px;
		width: 32px;
		height: 32px;
		background-size: contain;
	}

	.details {
		flex-grow: 1;
		flex-shrink: 1;
		min-width: 0;
		flex-basis: 50%;
		line-height: 110%;
		padding: 2px;
	}

	.filename {
		display: flex;
		.basename {
			white-space: nowrap;
			overflow: hidden;
			text-overflow: ellipsis;
			padding-bottom: 2px;
		}
		.extension {
			opacity: 0.7;
		}
	}

	.attachment--info,
	.filesize, .filedate {
		font-size: 90%;
		color: var(--color-text-maxcontrast);
	}

	.app-popover-menu-utils {
		position: relative;
		right: -10px;
		button {
			height: 32px;
			width: 42px;
		}
	}
}

.attachments-infobox {
	position: sticky;
	bottom: 0;

	display: flex;
	align-items: center;
	justify-content: flex-start;

	background-color: var(--color-background-dark);
	border-radius: var(--border-radius-large);
	padding: 1em;
	margin-top: 20px;

	.content {
		display: flex;
		align-items: center;
		justify-content: flex-start;

		margin-left: 1em;
		margin-bottom: 0;
	}
}
</style>
