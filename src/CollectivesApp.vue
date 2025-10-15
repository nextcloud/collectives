<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="collectives">
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
		<NavigationBar v-if="!printView" />
		<router-view />
		<PageSidebar v-if="currentCollective && currentPage" />
		<CollectiveSettings
			v-if="showCollectiveSettings"
			:collective="settingsCollective" />
	</NcContent>
</template>

<script>
import { NcContent } from '@nextcloud/vue'
import { mapActions, mapState } from 'pinia'
import CollectiveSettings from './components/Nav/CollectiveSettings.vue'
import NavigationBar from './components/NavigationBar.vue'
import PageSidebar from './components/PageSidebar.vue'
import { useNetworkState } from './composables/useNetworkState.js'
import { useCollectivesStore } from './stores/collectives.js'
import { usePagesStore } from './stores/pages.js'
import { useRootStore } from './stores/root.js'
import { useSettingsStore } from './stores/settings.js'
import displayError from './util/displayError.js'

export default {
	name: 'CollectivesApp',

	components: {
		CollectiveSettings,
		NcContent,
		NavigationBar,
		PageSidebar,
	},

	setup() {
		const rootStore = useRootStore()
		const { networkOnline } = useNetworkState()
		return { networkOnline, rootStore }
	},

	data() {
		return {
			loadPending: true,
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
				this.rootStore.fileIdQuery = val.query.fileId
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
		this.getCollectivesAndSettings()
	},

	methods: {
		...mapActions(useSettingsStore, ['getCollectivesFolder']),
		...mapActions(useCollectivesStore, [
			'getCollectives',
			'getTrashCollectives',
		]),

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
				const promises = [this.getCollectivesFolder()]
				promises.push(this.getTrashCollectives())

				try {
					await Promise.all(promises)
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
