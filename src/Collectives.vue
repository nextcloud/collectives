<template>
	<NcContent app-name="collectives">
		<Navigation v-if="!printView" />
		<router-view />
		<PageSidebar v-if="currentCollective && currentPage" />
		<CollectiveSettings v-if="showCollectiveSettings"
			:collective="settingsCollective" />
	</NcContent>
</template>

<script>
import { showInfo } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapState } from 'vuex'
import { GET_COLLECTIVES_FOLDER, GET_COLLECTIVES, GET_TRASH_COLLECTIVES } from './store/actions.js'
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

	computed: {
		...mapState([
			'printView',
			'messages',
		]),

		...mapGetters([
			'currentCollective',
			'currentPage',
			'isPublic',
			'showing',
			'settingsCollective',
		]),

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
				this.$store.commit('info', null)
			}
		},
	},

	mounted() {
		this.getCollectives()
		if (!this.isPublic) {
			this.getCollectivesFolder()
			this.getTrashCollectives()
		}
	},

	methods: {
		...mapActions({
			dispatchGetCollectives: GET_COLLECTIVES,
			dispatchGetCollectivesFolder: GET_COLLECTIVES_FOLDER,
			dispatchGetTrashCollectives: GET_TRASH_COLLECTIVES,
		}),

		/**
		 * Get collective folder for user
		 *
		 * @return {Promise}
		 */
		getCollectivesFolder() {
			return this.dispatchGetCollectivesFolder()
				.catch(displayError('Could not fetch collectives folder'))
		},

		/**
		 * Get list of all collectives
		 *
		 * @return {Promise}
		 */
		getCollectives() {
			return this.dispatchGetCollectives()
				.catch(displayError('Could not fetch collectives'))
		},

		/**
		 * Get list of all collectives in trash
		 *
		 * @return {Promise}
		 */
		getTrashCollectives() {
			return this.dispatchGetTrashCollectives()
				.catch(displayError('Could not fetch collectives from trash'))
		},
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
	font-size: 30px;
	border: none;
	color: var(--color-main-text);
	width: 100%;
	height: 43px;
	opacity: 0.8;
	text-overflow: unset;

	&.mobile {
		font-size: 30px;
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

/* Align toggle with page list header bar */
.app-navigation .app-navigation-toggle {
	top: 0 !important;
}

/* Fix for anchor scroll and sticky header */
.splitpanes__pane-details {
	scroll-padding-top: 60px;
	scroll-behavior: smooth;
}
</style>

<style lang="scss" scoped>
:deep(.page-sidebar-button) {
	// Align sidebar toggle button with page title bar
	margin-top: 2px;
}

:deep(.page-sidebar-button_mobile) {
	// Hide on mobile (moved to page actions instead there to save horizontal space
	display: none;
}
</style>
