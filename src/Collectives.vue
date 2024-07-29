<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcContent app-name="collectives">
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
import { showInfo } from '@nextcloud/dialogs'
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
			'messages',
			'printView',
			'shareTokenParam',
			'showing',
		]),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'settingsCollective',
		]),
		...mapState(usePagesStore, ['currentPage']),

		info() {
			return this.messages.info
		},

		showCollectiveSettings() {
			return !!this.settingsCollective
		},
	},

	watch: {
		'info'(current) {
			if (current) {
				showInfo(current)
				this.rootStore.info(null)
			}
		},
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

#titleform {
	z-index: 10022;
}

#version-title, #titleform input[type='text'] {
	border: none;
	color: var(--color-main-text);
	width: 100%;
	height: 43px;
	opacity: 0.8;
	text-overflow: unset;

	&.mobile {
		// Less padding to save some extra space
		padding: 0;
		padding-right: 4px;
	}
}

#titleform input[type='text']:disabled {
	color: var(--color-text-maxcontrast);
}

@page {
	size: auto;
	margin: 5mm;
}

@media print {
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
		display: block !important;
	}

	/* TODO: remove first selector once removing LegacyEditor.vue+Reader.vue */
	#text-wrapper #text .content-wrapper,
	[data-collectives-el='editor'] .content-wrapper,
	[data-collectives-el='reader'] .content-wrapper {
		display: block;

		div.ProseMirror {
			margin-top: 0;
			margin-bottom: 0;
			padding-top: 0;
			padding-bottom: 0;
		}
	}

}
</style>
