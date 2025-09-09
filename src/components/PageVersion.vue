<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="page-container">
		<div class="page-title-container"
			:class="{
				'full-width-view': isFullWidth,
				'sheet-view': !isFullWidth,
			}"
			data-cy-collective="page-title-container">
			<div class="page-title-icon">
				<div v-if="currentPage.emoji">
					{{ currentPage.emoji }}
				</div>
				<EmoticonIcon v-else
					class="emoji-picker-emoticon"
					:size="pageTitleIconSize"
					fill-color="var(--color-text-maxcontrast)" />
			</div>

			<PageTitle class="title title-version"
				:value="versionTitle"
				:disabled="true" />
			<NcButton :title="t('collectives', 'Restore this version')"
				:aria-label="t('collectives', 'Restore this version')"
				class="titleform-button"
				@click="onRestoreVersion">
				<template #icon>
					<RestoreIcon :size="20" />
				</template>
				{{ t('collectives', 'Restore') }}
			</NcButton>
			<NcActions>
				<NcActionButton :close-after-click="true" @click="closeVersions">
					<template #icon>
						<DockRightIcon :size="20" />
					</template>
				</NcActionButton>
			</NcActions>
		</div>
		<SkeletonLoading v-show="!contentLoaded" class="page-content-skeleton" type="text" />
		<div v-show="contentLoaded"
			id="text-container"
			:class="[isFullWidth ? 'full-width-view' : 'sheet-view']">
			<div ref="readerEl" data-collectives-el="reader" data-cy-collectives="reader" />
		</div>
	</div>
</template>

<script>
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import pageContentMixin from '../mixins/pageContentMixin.js'

import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { usePagesStore } from '../stores/pages.js'
import { useVersionsStore } from '../stores/versions.js'

import { NcActionButton, NcActions, NcButton } from '@nextcloud/vue'
import DockRightIcon from 'vue-material-design-icons/DockRight.vue'
import EmoticonIcon from 'vue-material-design-icons/EmoticonOutline.vue'
import RestoreIcon from 'vue-material-design-icons/Restore.vue'
import PageTitle from './Page/PageTitle.vue'
import SkeletonLoading from './SkeletonLoading.vue'
import { useEditor } from '../composables/useEditor.js'

export default {
	name: 'PageVersion',

	components: {
		DockRightIcon,
		EmoticonIcon,
		NcActionButton,
		NcActions,
		NcButton,
		PageTitle,
		RestoreIcon,
		SkeletonLoading,
	},

	mixins: [
		isMobile,
		pageContentMixin,
	],

	setup() {
		const { davContent, reader, readerEl, setupReader } = useEditor()
		return { davContent, reader, readerEl, setupReader }
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useVersionsStore, ['selectedVersion']),
		...mapState(usePagesStore, [
			'currentPage',
			'title',
		]),

		isFullWidth() {
			return this.currentPage.isFullWidth
		},

		pageTitleIconSize() {
			return isMobile ? 25 : 30
		},

		versionTitle() {
			return `${this.title} (${this.selectedVersion.basename})`
		},

		contentLoaded() {
			return !!this.davContent || !this.loading(`version-${this.currentPage.id}-${this.selectedVersion.mtime}`)
		},
	},

	watch: {
		'selectedVersion.mtime'() {
			this.davContent = ''
			this.getPageContent()
		},
	},

	mounted() {
		this.pageInfoBarPage = {}
		this.setupReader()
		this.getPageContent()
	},

	methods: {
		...mapActions(useRootStore, ['done', 'hide', 'load']),
		...mapActions(useVersionsStore, [
			'getVersions',
			'restoreVersion',
			'selectVersion',
		]),

		closeVersions() {
			this.selectVersion(null)
			this.hide('sidebar')
		},
		/**
		 * Revert page to an old version
		 */
		async onRestoreVersion() {
			this.restoreVersion(this.selectedVersion)
		},

		async getPageContent() {
			this.load(`version-${this.currentPage.id}-${this.selectedVersion.mtime}`)
			this.davContent = await this.fetchPageContent(this.selectedVersion.url)
			this.reader?.setContent(this.davContent)
			this.done(`version-${this.currentPage.id}-${this.selectedVersion.mtime}`)
		},
	},
}
</script>

<style lang="scss" scoped>
.page-title-container {
	display: flex;
	max-width: 100%;
	min-height: 48px;
	padding: 0 8px;
	align-items: center;
	background-color: var(--color-main-background);

	&.sheet-view {
		margin: 0 0 0 max(0px, calc(50% - (var(--text-editor-max-width) / 2)));
	}

	.button-emoji-page {
		font-size: 0.8em;
	}
}

.page-title-icon {
	font-size: 1.8em;
}

.title-version :deep(input[type="text"]) {
	color: var(--color-text-maxcontrast);
}

.page-content-skeleton {
	padding-top: var(--default-clickable-area);
}
</style>
