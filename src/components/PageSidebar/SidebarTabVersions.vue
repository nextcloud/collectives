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
			<NcButton
				v-if="hasComparableHistoricalVersions"
				wide
				class="versions-container__compare"
				@click="onOpenComparisonSelector">
				<template #icon>
					<FileCompareIcon :size="22" />
				</template>
				{{ t('collectives', 'Compare versions…') }}
			</NcButton>
			<ul :aria-label="t('collectives', 'Page versions')" class="version-list">
				<VersionEntry
					v-for="version in sortedVersions"
					:key="version.mtime"
					:version
					:isCurrent="isCurrent(version.mtime)"
					:isSelected="isSelected(version.mtime)"
					:isFirstVersion="version.mtime === initialVersionMtime"
					:isLoading="loading(`version-${pageId}-${version.mtime}`)"
					:canEdit="currentCollectiveCanEdit"
					@click="onOpenVersion(version)"
					@startLabelUpdate="onStartLabelUpdate(version)"
					@compare="onCompareVersion(version, $event)"
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

		<!-- label dialog -->
		<VersionLabelDialog
			v-if="editedVersion"
			v-model:open="showVersionLabelForm"
			:versionLabel="editedVersion.label"
			@labelUpdate="onLabelUpdate" />

		<VersionComparisonDialog
			ref="comparisonDialog"
			:currentVersion
			:versions
			:filePath="`/${currentPageFilePath}`"
			@retryRoute="onRetryComparisonRoute" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { mapActions, mapState } from 'pinia'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import AlertOctagonIcon from 'vue-material-design-icons/AlertOctagonOutline.vue'
import BackupRestoreIcon from 'vue-material-design-icons/BackupRestore.vue'
import FileCompareIcon from 'vue-material-design-icons/FileCompare.vue'
import OfflineContent from './OfflineContent.vue'
import VersionComparisonDialog from './VersionComparisonDialog.vue'
import VersionEntry from './VersionEntry.vue'
import VersionLabelDialog from './VersionLabelDialog.vue'
import { useNetworkState } from '../../composables/useNetworkState.ts'
import { useCollectivesStore } from '../../stores/collectives.js'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'
import { useVersionsStore } from '../../stores/versions.js'
import { createVersionComparisonState } from '../../util/versionComparison.js'
import { parseVersionComparisonRoute } from '../../util/versionComparisonRoute.js'

export default {
	name: 'SidebarTabVersions',

	components: {
		AlertOctagonIcon,
		NcEmptyContent,
		NcLoadingIcon,
		BackupRestoreIcon,
		FileCompareIcon,
		NcButton,
		OfflineContent,
		VersionComparisonDialog,
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
			loadGeneration: 0,
			error: '',
			showVersionLabelForm: false,
			editedVersion: null,
		}
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(usePagesStore, ['currentPageFilePath']),
		...mapState(useCollectivesStore, ['currentCollectiveCanEdit']),
		...mapState(useVersionsStore, [
			'currentVersion',
			'loadedPageId',
			'selectedVersion',
			'versions',
		]),

		pageMtime() {
			return this.pageTimestamp * 1000
		},

		sortedVersions() {
			return this.versions.toSorted((a, b) => {
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

		hasComparableHistoricalVersions() {
			return createVersionComparisonState(this.currentVersion, this.versions)
				.options.some(({ kind }) => kind === 'historical')
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
			this.$refs.comparisonDialog?.closeDialog()
			this.getPageVersions()
		},

		pageTimestamp: function(value, previous) {
			if (value && value !== previous) {
				this.getPageVersions()
			}
		},

		'$route.fullPath': 'syncComparisonRoute',

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
		t,

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
			const pageId = this.pageId
			const generation = ++this.loadGeneration
			this.loadPending = true
			if (!this.networkOnline) {
				this.done('versions')
				return
			}

			this.load('versions')
			this.error = ''
			try {
				await this.getVersions(pageId)
				if (generation !== this.loadGeneration) {
					return
				}
				this.loadPending = false
				await this.$nextTick()
				this.syncComparisonRoute()
			} catch (e) {
				if (generation !== this.loadGeneration) {
					return
				}
				this.error = t('collectives', 'Could not get page versions')
				console.error('Failed to get page versions', e)
			} finally {
				if (generation === this.loadGeneration) {
					this.done('versions')
				}
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

		onCompareVersion(version, event) {
			this.$refs.comparisonDialog.openFor(version, event.currentTarget)
		},

		onOpenComparisonSelector(event) {
			this.$refs.comparisonDialog.openSelector(event.currentTarget)
		},

		async onRetryComparisonRoute() {
			await this.getPageVersions()
		},

		syncComparisonRoute() {
			const comparisonRoute = parseVersionComparisonRoute(this.$route.query)
			if (comparisonRoute.kind === 'absent') {
				this.$refs.comparisonDialog?.closeFromRoute()
				return
			}
			if (comparisonRoute.kind !== 'valid' || this.loadPending || this.loadedPageId !== this.pageId) {
				return
			}
			this.$nextTick(() => this.$refs.comparisonDialog?.openRoutedPair(comparisonRoute))
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

<style scoped lang="scss">
.versions-container__compare {
	margin-block-end: 8px;
}
</style>
