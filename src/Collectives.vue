<template>
	<NcContent app-name="collectives">
		<Navigation v-if="!printView" />
		<router-view />
		<PageSidebar v-if="currentPage" v-show="showing('sidebar')" />
	</NcContent>
</template>

<script>
import { showInfo, showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapState } from 'vuex'
import { GET_COLLECTIVES_FOLDER, GET_COLLECTIVES, GET_TRASH_COLLECTIVES } from './store/actions.js'
import displayError from './util/displayError.js'
import { NcContent } from '@nextcloud/vue'
import Navigation from './components/Navigation.vue'
import PageSidebar from './components/PageSidebar.vue'

export default {
	name: 'Collectives',

	components: {
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
			'isPublic',
			'showing',
			'currentPage',
		]),
		info() {
			return this.messages.info
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

<style>
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

.app-navigation .app-navigation-toggle {
	top: 0 !important;
}
</style>
