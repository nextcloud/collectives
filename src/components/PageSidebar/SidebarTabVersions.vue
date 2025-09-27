<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="versions-container">
		<!-- loading -->
		<NcEmptyContent v-if="loading('versions')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- offline -->
		<OfflineContent v-else-if="!networkOnline" />

		<!-- error message -->
		<NcEmptyContent v-else-if="error" :name="error">
			<template #icon>
				<AlertOctagonIcon />
			</template>
		</NcEmptyContent>

		<!-- versions list -->
		<div v-else-if="!loading('versions') && sortedVersions.length">
			<ul :aria-label="t('collectives', 'Page versions')" class="version-list">
				<VersionEntry
					v-for="version in sortedVersions"
					:key="version.mtime"
					:version="version"
					:is-current="isCurrent(version.mtime)"
					:is-selected="isSelected(version.mtime)"
					:is-first-version="version.mtime === initialVersionMtime"
					:is-loading="loading(`version-${pageId}-${version.mtime}`)"
					:can-edit="currentCollectiveCanEdit"
					@click="onOpenVersion(version)"
					@start-label-update="onStartLabelUpdate(version)"
					@compare="onCompareVersion(version)"
					@restore="onRestoreVersion(version)"
					@delete="onDeleteVersion(version)" />
			</ul>
		</div>

		<!-- no versions found -->
		<NcEmptyContent
			v-else
			:name="t('collectives', 'No other versions available')"
			:description="t('collectives', 'After editing you can find old versions of the page here.')">
			<template #icon>
				<BackupRestoreIcon />
			</template>
		</NcEmptyContent>
		<VersionLabelDialog
			v-if="editedVersion"
			:open.sync="showVersionLabelForm"
			:version-label="editedVersion.label"
			@label-update="onLabelUpdate" />
	</div>
</template>

<script>
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagonOutline.vue'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import OfflineContent from './OfflineContent.vue'
import VersionEntry from './VersionEntry.vue'
import VersionLabelDialog from './VersionLabelDialog.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'
import { useVersionsStore } from '../../stores/versions.js'

export default {
	name: 'SidebarTabVersions',

	components: {
		AlertOctagonIcon,
		NcEmptyContent,
		NcLoadingIcon,
		BackupRestoreIcon,
		OfflineContent,
		VersionEntry,
		VersionLabelDialog,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},

		pageTimestamp: {
			type: Number,
			required: true,
		},
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	data() {
		return {
			loadPending: true,
			error: '',
			showVersionLabelForm: false,
			editedVersion: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),
		...mapState(useVersionsStore, [
			'currentVersion',
			'selectedVersion',
			'versions',
		]),

		pageMtime() {
			return this.pageTimestamp * 1000
		},

		sortedVersions() {
			return [...this.versions].sort((a, b) => {
				if (a.mtime === this.pageMtime) {
					return -1
				} else if (b.mtime === this.pageMtime) {
					return 1
				} else {
					return b.mtime - a.mtime
				}
			})
		},

		initialVersionMtime() {
			return this.versions
				.map((version) => version.mtime)
				.reduce((a, b) => Math.min(a, b))
		},

		isCurrent() {
			return (mtime) => mtime === this.pageMtime
		},

		isSelected() {
			return (mtime) => {
				return this.isCurrent(mtime)
					? !this.selectedVersion
					: mtime === this.selectedVersion?.mtime
			}
		},
	},

	watch: {
		pageId: function() {
			this.getPageVersions()
		},

		networkOnline: function(val) {
			if (val && this.loadPending) {
				this.getPageVersions()
			}
		},
	},

	beforeMount() {
		this.getPageVersions()
	},

	methods: {
		...mapActions(useRootStore, ['load', 'done']),
		...mapActions(useVersionsStore, [
			'deleteVersion',
			'getVersions',
			'restoreVersion',
			'selectVersion',
			'setVersionLabel',
		]),

		/**
		 * Get versions of a page
		 */
		async getPageVersions() {
			this.loadPending = true
			if (!this.networkOnline) {
				return
			}

			this.load('versions')
			try {
				await this.getVersions(this.pageId)
				this.loadPending = false
			} catch (e) {
				this.error = t('collectives', 'Could not get page versions')
				console.error('Failed to get page versions', e)
			} finally {
				this.done('versions')
			}
		},

		onOpenVersion(version) {
			if (this.isCurrent(version.mtime)) {
				this.selectVersion(null)
			} else {
				this.selectVersion(version)
			}
		},

		onStartLabelUpdate(version) {
			this.showVersionLabelForm = true
			this.editedVersion = version
		},

		async onLabelUpdate(newLabel) {
			const oldLabel = this.editedVersion.label
			this.editedVersion.label = newLabel
			this.showVersionLabelForm = false

			try {
				await this.setVersionLabel(this.editedVersion, newLabel)
				this.editedVersion = null
			} catch {
				this.editedVersion.label = oldLabel
			}
		},

		onCompareVersion(version) {
			OCA.Viewer.compare(this.currentVersion, this.versions.find((v) => v.source === version.source))
		},

		async onRestoreVersion(version) {
			await this.restoreVersion(version)
		},

		async onDeleteVersion(version) {
			await this.deleteVersion(version)
		},
	},
}
</script>
