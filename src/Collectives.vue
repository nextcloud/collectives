<template>
	<Content app-name="collectives">
		<!-- go back to list when in details mode -->
		<a v-if="showing('details') && isMobile"
			class="app-details-toggle icon-toggle-filelist"
			href="#"
			@click.stop.prevent="hide('details')" />
		<Nav v-if="!printView" />
		<router-view />
		<PageSidebar v-if="currentPage" v-show="showing('sidebar')" />
		<CollectiveSettings v-if="showCollectiveSettings"
			:collective="settingsCollective" />
	</Content>
</template>

<script>
import { showInfo, showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { GET_COLLECTIVES_FOLDER, GET_COLLECTIVES, GET_TRASH_COLLECTIVES } from './store/actions.js'
import displayError from './util/displayError.js'
import CollectiveSettings from './components/Nav/CollectiveSettings.vue'
import Content from '@nextcloud/vue/dist/Components/Content'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import Nav from './components/Nav.vue'
import PageSidebar from './components/PageSidebar.vue'

export default {
	name: 'Collectives',

	components: {
		CollectiveSettings,
		Content,
		Nav,
		PageSidebar,
	},

	mixins: [
		isMobile,
	],

	computed: {
		...mapState([
			'printView',
			'messages',
		]),

		...mapGetters([
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

		if (!this.isPublic && !('contacts' in this.OC.appswebroots)) {
			console.error('The contacts app is required to manage members')
			showError(t('collectives', 'The contacts app is required to manage members'))
		}
	},

	methods: {
		...mapMutations(['hide']),

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
#app-content-wrapper {
	display: flex;
	position: relative;
	align-items: stretch;
	min-height: 100%;
}

.app-content-wrapper .app-details-toggle.icon-confirm {
	display: none;
}

#editor-container #editor-wrapper {
	position: static;
	overflow: visible;
}

#editor-container #editor {
	max-width: 800px;
	margin-left: auto;
	margin-right: auto;
	overflow: visible;
}

#editor-wrapper #editor div.ProseMirror {
	margin-top: 5px;
}

#text-wrapper #text div.ProseMirror {
	margin-top: 5px;
	padding-bottom: 200px;
}

#version-title, #titleform input[type='text'] {
	font-size: 35px;
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
	background-color: var(--color-main-background);
	color: var(--color-text-maxcontrast);
}

#action-menu {
	position: absolute;
	right: 0;
}

@page {
	size: auto;
	margin: 5mm;
}

@media print {
	#header {
		display: none !important;
	}

	#content-vue {
		display: block !important;
	}
}
</style>

<style lang="scss" scoped>
.app-details-toggle {
	position: sticky;
	top: 58px;
	width: 44px;
	height: 49px;
	margin-right: -44px;
	opacity: .6;
	z-index: 2000;
	&:active,
	&:hover,
	&:focus {
		opacity: 1;
	}
}
</style>
