<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="collectives">
		<input v-if="isPublic"
			id="isPublic"
			type="hidden"
			name="isPublic"
			value="1">
		<input v-if="isPublic"
			id="sharingToken"
			type="hidden"
			:value="shareTokenParam">
		<Navigation v-if="!printView" />
		<router-view />
		<PageSidebar v-if="currentCollective && currentPage" />
		<CollectiveSettings v-if="showCollectiveSettings"
			:collective="settingsCollective" />
	</NcContent>
</template>

<script>
import { mapActions, mapState } from 'pinia'
import { useRootStore } from './stores/root.js'
import { useSettingsStore } from './stores/settings.js'
import { useCollectivesStore } from './stores/collectives.js'
import { usePagesStore } from './stores/pages.js'
import displayError from './util/displayError.js'
import { NcContent } from '@nextcloud/vue'
import CollectiveSettings from './components/Nav/CollectiveSettings.vue'
import Navigation from './components/Navigation.vue'
import PageSidebar from './components/PageSidebar.vue'

export default {
	name: 'Collectives',

	components: {
		CollectiveSettings,
		NcContent,
		Navigation,
		PageSidebar,
	},

	setup() {
		const rootStore = useRootStore()
		return { rootStore }
	},

	computed: {
		...mapState(useRootStore, [
			'isPublic',
			'printView',
			'shareTokenParam',
			'showing',
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
				this.rootStore.pageParam = val.params.page
				this.rootStore.shareTokenParam = val.params.token
				this.rootStore.fileIdQuery = val.query.fileId
			},
			immediate: true,
		},
	},

	mounted() {
		this.rootStore.load('collective')
		this.getCollectives()
			.catch(displayError('Could not fetch collectives'))
		if (!this.isPublic) {
			this.getCollectivesFolder()
				.catch(displayError('Could not fetch collectives folder'))
			this.getTrashCollectives()
				.catch(displayError('Could not fetch collectives from trash'))
		}
	},

	methods: {
		...mapActions(useSettingsStore, ['getCollectivesFolder']),
		...mapActions(useCollectivesStore, [
			'getCollectives',
			'getTrashCollectives',
		]),
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
			margin-top: 0;
			margin-bottom: 0;
			padding-top: 0;
			padding-bottom: 0;
		}
	}

}
</style>
