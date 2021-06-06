<template>
	<Content app-name="collectives">
		<!-- go back to list when in details mode -->
		<a v-if="showing('details') && isMobile"
			class="app-details-toggle icon-toggle-filelist"
			href="#"
			@click.stop.prevent="hide('details')" />
		<Nav />
		<router-view />
		<PageSidebar v-if="currentPage" v-show="showing('sidebar')" />
	</Content>
</template>

<script>
import { showInfo } from '@nextcloud/dialogs'
import { mapState, mapGetters, mapMutations } from 'vuex'
import { GET_COLLECTIVES, GET_TRASH_COLLECTIVES } from './store/actions'
import displayError from './util/displayError'
import Content from '@nextcloud/vue/dist/Components/Content'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import Nav from './components/Nav'
import PageSidebar from './components/PageSidebar'

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
			'messages',
		]),
		...mapGetters([
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
		this.getTrashCollectives()
	},

	methods: {
		...mapMutations(['hide']),

		/**
		 * Get list of all collectives
		 * @returns {Promise}
		 */
		getCollectives() {
			return this.$store.dispatch(GET_COLLECTIVES)
				.catch(displayError('Could not fetch collectives'))
		},

		/**
		 * Get list of all collectives in trash
		 * @returns {Promise}
		 */
		getTrashCollectives() {
			return this.$store.dispatch(GET_TRASH_COLLECTIVES)
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

	#titleform button {
		margin-top: 0px;
	}

	.app-content-details div #editor-container,
	.app-content-details div #text-container {
		position: absolute;
		top: 54px;
		height: calc(100% - 56px);
	}

	#editor-container #editor {
		max-width: 800px;
		margin-left: auto;
		margin-right: auto;
	}

	#editor-container #editor .menubar {
		z-index: 100;
	}

	#editor-wrapper #editor div.ProseMirror {
		margin-top: 5px;
		padding-bottom: 50px;
	}

	#text-wrapper #text div.ProseMirror {
		margin-top: 5px;
		padding-bottom: 50px;
	}

	#version-title, #titleform input[type='text'] {
		font-size: 35px;
		border: none;
		color: var(--color-main-text);
		width: 100%;
		height: 43px;
		opacity: 0.8;
	}

	.page-title {
		width: 100%;
	}

	#titleform input[type='text']:disabled {
		background-color: var(--color-main-background);
		color: var(--color-text-lighter);
		margin: 3px 3px 3px 0;
		padding: 7px 6px;
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>

<style lang="scss" scoped>
.app-details-toggle {
	position: absolute;
	width: 44px;
	height: 44px;
	padding: 14px;
	cursor: pointer;
	opacity: .6;
	font-size: 16px;
	line-height: 17px;
	// background-color: var(--color-main-background);
	z-index: 2000;
	&:active,
	&:hover,
	&:focus {
		opacity: 1;
	}
	// Hide app-navigation toggle if shown
	&::v-deep + .app-navigation .app-navigation-toggle {
		display: none;
	}
}
</style>
