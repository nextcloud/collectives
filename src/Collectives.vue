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
	</Content>
</template>

<script>
import { showInfo, showError } from '@nextcloud/dialogs'
import { mapActions, mapGetters, mapMutations, mapState } from 'vuex'
import { GET_COLLECTIVES_FOLDER, GET_COLLECTIVES, GET_TRASH_COLLECTIVES } from './store/actions.js'
import displayError from './util/displayError.js'
import Content from '@nextcloud/vue/dist/Components/Content'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import Nav from './components/Nav.vue'
import PageSidebar from './components/PageSidebar.vue'

export default {
	name: 'Collectives',

	components: {
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

<style>
.app-content-details {
	position: relative;
	flex: 1 1 524px;
}

#app-content-wrapper {
	display: flex;
	position: relative;
	align-items: stretch;
	min-height: 100%;
}

#titleform {
	z-index: 1;
}

.app-content-details div #editor-container {
	position: absolute;
	top: 60px;
	height: calc(100% - 60px);
}

.app-content-details div #text-container {
	position: absolute;
	top: 60px;
	height: calc(100% - 60px);
	max-width: 100%;
}

#editor-container #editor {
	max-width: 800px;
	margin-left: auto;
	margin-right: auto;
}

#editor-container #editor .menubar {
	z-index: 100;
	max-width: 670px;
	margin: auto;
}

#editor-wrapper #editor div.ProseMirror {
	margin-top: 5px;
}

#text-wrapper #text div.ProseMirror {
	margin-top: 5px;
	padding-bottom: 50px;
	/* Add 2x (21px + 14px) for at least two trailing breaks */
	padding-bottom: calc(200px + 70px);
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
</style>

<style lang="scss" scoped>
.app-details-toggle {
	position: absolute;
	width: 44px;
	height: 49px;
	margin: 8px 0 2px 0;
	opacity: .6;
	z-index: 2000;
	&:active,
	&:hover,
	&:focus {
		opacity: 1;
	}
	// Hide app-navigation toggle if app-details toggle is shown
	&::v-deep + .app-navigation .app-navigation-toggle {
		display: none;
	}
}
</style>
