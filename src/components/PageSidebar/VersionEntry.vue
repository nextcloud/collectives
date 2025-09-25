<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem
		:name="version.basename"
		class="version"
		:class="{ active: isSelected }"
		:active="isSelected"
		:force-display-actions="true"
		:actions-aria-label="t('collectives', 'Actions for versions from {versionHumanExplicitDate}', { versionHumanExplicitDate })"
		@click="$emit('click')">
		<!-- Icon -->
		<template #icon>
			<NcLoadingIcon
				v-if="isLoading"
				:size="26"
				fill-color="var(--color-main-background)"
				class="version-icon version-icon__loading" />
			<PageIcon
				v-else
				:size="26"
				fill-color="var(--color-main-background)"
				class="version-icon version-icon__page" />
		</template>

		<!-- Name and author -->
		<template #name>
			<div class="version-info">
				<div
					v-if="versionLabel"
					class="version-info__label"
					:title="versionLabel">
					{{ versionLabel }}
				</div>
				<div
					v-if="versionAuthor"
					class="version-info">
					<span v-if="versionLabel">•</span>
					<NcAvatar
						class="avatar"
						:user="version.author"
						:size="20"
						disable-menu
						disable-tooltip
						:hide-status="true" />
					<div>{{ versionAuthor }}</div>
				</div>
			</div>
		</template>

		<!-- Version file size as subline -->
		<template #subname>
			<div class="version-info version-info__subline">
				<NcDateTime
					class="version-info__date"
					relative-time="short"
					:timestamp="version.mtime" />
				<!-- separate dot to improve alignment -->
				<span>•</span>
				<span>{{ humanReadableSize }}</span>
			</div>
		</template>

		<!-- Actions -->
		<template #actions>
			<NcActionButton
				:close-after-click="true"
				@click="$emit('start-label-update')">
				<template #icon>
					<PencilIcon :size="22" />
				</template>
				{{ version.label === '' ? t('collectives', 'Name this version') : t('collectives', 'Edit version name') }}
			</NcActionButton>
			<NcActionButton
				v-if="!isCurrent"
				:close-after-click="true"
				@click="$emit('compare')">
				<template #icon>
					<FileCompareIcon :size="22" />
				</template>
				{{ t('collectives', 'Compare to current version') }}
			</NcActionButton>
			<NcActionButton
				v-if="!isCurrent && canEdit"
				:close-after-click="true"
				@click="$emit('restore')">
				<template #icon>
					<BackupRestoreIcon :size="22" />
				</template>
				{{ t('collectives', 'Restore version') }}
			</NcActionButton>
			<NcActionLink
				:href="version.source"
				:close-after-click="true"
				:download="version.source">
				<template #icon>
					<DownloadIcon :size="22" />
				</template>
				{{ t('collectives', 'Download version') }}
			</NcActionLink>
			<NcActionButton
				v-if="!isCurrent && canEdit"
				:close-after-click="true"
				@click="$emit('delete')">
				<template #icon>
					<DeleteIcon :size="22" />
				</template>
				{{ t('collectives', 'Delete version') }}
			</NcActionButton>
		</template>
	</NcListItem>
</template>

<script>
import { getCurrentUser } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
import { formatFileSize } from '@nextcloud/files'
import moment from '@nextcloud/moment'
import { generateUrl } from '@nextcloud/router'
import { NcActionButton, NcActionLink, NcAvatar, NcDateTime, NcListItem, NcLoadingIcon } from '@nextcloud/vue'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import FileCompareIcon from 'vue-material-design-icons/FileCompare.vue'
import PencilIcon from 'vue-material-design-icons/PencilOutline.vue'
import DeleteIcon from 'vue-material-design-icons/TrashCanOutline.vue'
import DownloadIcon from 'vue-material-design-icons/TrayArrowDown.vue'
import PageIcon from '../Icon/PageIcon.vue'

export default {
	name: 'VersionEntry',

	components: {
		BackupRestoreIcon,
		DeleteIcon,
		DownloadIcon,
		FileCompareIcon,
		NcActionButton,
		NcActionLink,
		NcAvatar,
		NcDateTime,
		NcListItem,
		NcLoadingIcon,
		PageIcon,
		PencilIcon,
	},

	props: {
		version: {
			type: Object,
			required: true,
		},

		isCurrent: {
			type: Boolean,
			default: false,
		},

		isFirstVersion: {
			type: Boolean,
			default: false,
		},

		isSelected: {
			type: Boolean,
			default: false,
		},

		isLoading: {
			type: Boolean,
			default: false,
		},

		canEdit: {
			type: Boolean,
			default: false,
		},
	},

	data() {
		return {
			versionAuthor: '',
		}
	},

	computed: {
		humanReadableSize() {
			return formatFileSize(this.version.size)
		},

		versionLabel() {
			const label = this.version.label ?? ''
			if (this.isCurrent) {
				if (label === '') {
					return t('collectives', 'Current version')
				} else {
					return `${label} (${t('collectives', 'Current version')})`
				}
			}

			if (this.isFirstVersion && label === '') {
				return t('collectives', 'Initial version')
			}

			return label
		},

		versionHumanExplicitDate() {
			return moment(this.version.mtime).format('LLLL')
		},
	},

	beforeMount() {
		this.fetchDisplayName()
	},

	methods: {
		async fetchDisplayName() {
			this.versionAuthor = ''
			if (!this.version.author) {
				return
			}

			if (this.version.author === getCurrentUser()?.uid) {
				this.versionAuthor = t('collectives', 'You')
			} else {
				try {
					const { data } = await axios.post(generateUrl('/displaynames'), { users: [this.version.author] })
					this.versionAuthor = data.users[this.version.author]
				} catch (error) {
					console.warn('Could not load user display name', { error })
				}
			}
		},
	},
}
</script>

<style scoped lang="scss">
.version {
	display: flex;
	flex-direction: row;

	.version-icon {
		height: 34px;
		border-radius: var(--border-radius);

		&__page {
			background-color: var(--color-background-darker);
		}
	}

	.version-info {
		display: flex;
		flex-direction: row;
		align-items: center;
		gap: 0.5rem;
		font-weight: 500;

		&__label {
			font-weight: 700;
			// Fix overflow on narrow screens
			overflow: hidden;
			text-overflow: ellipsis;
		}

		&__date {
			// Fix overflow on narrow screens
			overflow: hidden;
			text-overflow: ellipsis;
		}
	}
}
</style>
