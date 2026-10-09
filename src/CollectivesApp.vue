<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent appName="collectives">
		<input
			v-if="isPublic"
			id="isPublic"
			type="hidden"
			name="isPublic"
			value="1">
		<input
			v-if="isPublic"
			id="sharingToken"
			type="hidden"
			:value="shareTokenParam">
		<NcAppNavigation
			v-if="!printView"
			:style="isMobile ? undefined : { width: `${navWidth}px`, '--collectives-nav-width': `${navWidth}px` }">
			<template #list>
				<CollectiveSelector />
				<PageList v-if="currentCollective" />
				<div
					v-if="!isMobile"
					class="app-navigation-resize-handle"
					@pointerdown="onResizeStart" />
			</template>
		</NcAppNavigation>
		<router-view />
		<PageSidebar v-if="currentCollective && currentPage" />
		<CollectiveSettings
			v-if="showCollectiveSettings"
			:collective="settingsCollective" />
		<NewCollectiveModal v-if="showNewCollectiveModal" @close="onCloseNewCollectiveModal" />
	</NcContent>
</template>

<script>
import { subscribe, unsubscribe } from '@nextcloud/event-bus'
import { loadState } from '@nextcloud/initial-state'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcContent from '@nextcloud/vue/components/NcContent'
import CollectiveSelector from './components/Nav/CollectiveSelector.vue'
import CollectiveSettings from './components/Nav/CollectiveSettings.vue'
import NewCollectiveModal from './components/Nav/NewCollectiveModal.vue'
import PageList from './components/PageList.vue'
import PageSidebar from './components/PageSidebar.vue'
import { useNetworkState } from './composables/useNetworkState.js'
import { useCollectivesStore } from './stores/collectives.js'
import { usePagesStore } from './stores/pages.js'
import { useRootStore } from './stores/root.js'
import { useSettingsStore } from './stores/settings.js'
import displayError from './util/displayError.js'
import { clampNavWidth, NAV_WIDTH_DEFAULT } from './util/navWidth.js'

export default {
	name: 'CollectivesApp',

	components: {
		CollectiveSelector,
		CollectiveSettings,
		NcAppNavigation,
		NcContent,
		NewCollectiveModal,
		PageList,
		PageSidebar,
	},

	setup() {
		const rootStore = useRootStore()
		const { networkOnline } = useNetworkState()
		const isMobile = useIsMobile()
		return { isMobile, networkOnline, rootStore }
	},

	data() {
		return {
			loadPending: true,
			showNewCollectiveModal: false,
			navWidth: NAV_WIDTH_DEFAULT,
			resizeStartX: 0,
			resizeStartWidth: 0,
		}
	},

	computed: {
		...mapState(useRootStore, [
			'isPublic',
			'printView',
			'shareTokenParam',
		]),

		...mapState(useCollectivesStore, [
			'currentCollective',
			'settingsCollective',
		]),

		...mapState(usePagesStore, ['currentPage']),

		showCollectiveSettings() {
			return !!this.settingsCollective
		},
	},

	watch: {
		$route: {
			handler(val) {
				this.rootStore.collectiveParam = val.params.collective
				this.rootStore.collectiveId = val.params.collectiveId ? parseInt(val.params.collectiveId) : null
				this.rootStore.pageParam = val.params.page
				this.rootStore.pageId = val.params.pageId ? parseInt(val.params.pageId) : null
				this.rootStore.shareTokenParam = val.params.token
				this.rootStore.fileIdQuery = val.query.fileId ? parseInt(val.query.fileId) : ''
			},

			immediate: true,
		},

		networkOnline: function(val) {
			if (val && this.loadPending) {
				this.getCollectivesAndSettings()
			}
		},
	},

	mounted() {
		this.loadAdminSettings()
		this.getCollectivesAndSettings()
		subscribe('open-new-collective-modal', this.onOpenNewCollectiveModal)
	},

	beforeUnmount() {
		unsubscribe('open-new-collective-modal', this.onOpenNewCollectiveModal)
		document.removeEventListener('pointermove', this.onResizeMove)
		document.removeEventListener('pointerup', this.onResizeEnd)
	},

	methods: {
		...mapActions(useSettingsStore, ['getCollectivesFolder']),
		...mapActions(useCollectivesStore, [
			'getCollectives',
		]),

		...mapActions(useRootStore, ['setPublishFeatureEnabled']),

		loadAdminSettings() {
			try {
				const publishEnabledState = loadState('collectives', 'publish_enabled', true)
				const isPublishEnabled = publishEnabledState === true || publishEnabledState === 'true'
				this.setPublishFeatureEnabled(isPublishEnabled)
			} catch (e) {
				console.error('Failed to load admin settings:', e)
			}
		},

		onOpenNewCollectiveModal() {
			this.showNewCollectiveModal = true
		},

		onCloseNewCollectiveModal() {
			this.showNewCollectiveModal = false
		},

		onResizeStart(event) {
			if (event.button !== 0) {
				return
			}
			event.preventDefault()
			this.resizeStartX = event.clientX
			this.resizeStartWidth = this.navWidth
			document.body.style.cursor = 'col-resize'
			document.body.style.userSelect = 'none'
			document.addEventListener('pointermove', this.onResizeMove)
			document.addEventListener('pointerup', this.onResizeEnd)
		},

		onResizeMove(event) {
			const direction = document.documentElement.dir === 'rtl' ? -1 : 1
			const delta = (event.clientX - this.resizeStartX) * direction
			this.navWidth = clampNavWidth(this.resizeStartWidth + delta)
		},

		onResizeEnd() {
			document.body.style.cursor = ''
			document.body.style.userSelect = ''
			document.removeEventListener('pointermove', this.onResizeMove)
			document.removeEventListener('pointerup', this.onResizeEnd)
		},

		async getCollectivesAndSettings() {
			this.loadPending = true
			if (!this.networkOnline) {
				return
			}

			try {
				await this.getCollectives()
			} catch (e) {
				displayError('Could not fetch collectives')(e)
				return
			}

			if (!this.isPublic) {
				try {
					await this.getCollectivesFolder()
				} catch (e) {
					displayError('Could not fetch collective details')(e)
				}
			}

			this.loadPending = false
		},
	},

}
</script>

<style lang="scss">
// Overrides NcAppNavigation's own closed-state margin, which is hardcoded to
// its 300px default width and doesn't account for our custom resized width
.app-navigation--closed {
	margin-inline-start: calc(-1 * var(--collectives-nav-width, 300px)) !important;
}

.app-navigation-resize-handle {
	position: absolute;
	top: 0;
	bottom: 0;
	inset-inline-end: 0;
	z-index: 100;
	width: 4px;
	cursor: col-resize;
	// Widen the grabbable area without widening the visible line
	background-clip: content-box;
	border-inline-start: 4px solid transparent;
	border-inline-end: 4px solid transparent;

	&:hover, &:active {
		background-color: var(--color-primary-element);
	}
}

.app-content-wrapper.app-content-wrapper--mobile {
	/* Required to allow scrolling long content on mobile */
	overflow-y: auto;
}

@media print {
	@page {
		margin: 10mm !important;
	}

	html, body {
		background: var(--color-main-background, white) !important;
	}

	/* hide toast notifications for printing */
	.toastify.dialogs {
		display: none;
	}

	#header {
		display: none !important;
	}

	#content-vue {
		margin: unset;
	}

	[data-collectives-el='editor'] .content-wrapper,
	[data-collectives-el='reader'] .content-wrapper {
		// Required to prevent newline between page title and content (due to `display: grid`)
		display: block !important;

		div.ProseMirror {
			height: unset;
			margin-block: 0;
			padding-block: 0;
		}
	}

}
</style>
