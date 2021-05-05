<template>
	<Content app-name="collective">
		<!-- go back to list when in details mode -->
		<a v-if="showing('details') && isMobile"
			class="app-details-toggle icon-toggle-filelist"
			href="#"
			@click.stop.prevent="hide('details')" />
		<Nav />
		<AppContent>
			<Collective v-if="collectiveParam" />
			<EmptyContent v-else-if="!isMobile" icon="icon-ant">
				{{ t('collectives', 'No collective selected') }}
				<template #desc>
					{{ t('collectives', 'Select a collective or create a new one on the left.') }}
				</template>
			</EmptyContent>
		</AppContent>
		<PageSidebar v-if="currentPage" v-show="showing('sidebar')" />
	</Content>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import { showInfo } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { GET_COLLECTIVES, GET_TRASH_COLLECTIVES } from '../store/actions'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Collective from '../components/Collective'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import Nav from '../components/Nav'
import PageSidebar from '../components/PageSidebar'
import displayError from '../util/displayError'

export default {
	name: 'CircleDash',

	components: {
		AppContent,
		Content,
		Collective,
		EmptyContent,
		Nav,
		PageSidebar,
	},

	mixins: [
		isMobile,
	],

	computed: {
		...mapGetters([
			'collectiveParam',
			'currentCollective',
			'currentPage',
			'messages',
			'pageParam',
			'showing',
			'version',
		]),

		info() {
			return this.messages.info
		},
	},

	watch: {
		'currentCollective'() {
			if (this.currentCollective) {
				this.getPages()
				this.closeNav()
				this.show('details')
			} else {
				this.openNav()
			}
		},
		'pageParam'() {
			this.$store.commit('version', null)
		},
		'info'() {
			if (this.info) {
				showInfo(this.info)
				this.$store.commit('info', null)
			}
		},
	},

	mounted() {
		this.openNav()
		this.getCollectives()
		this.getTrashCollectives()
		this.$nextTick(function() {
			this.openNav()
		})
	},

	methods: {
		...mapMutations(['show', 'hide']),
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

		/**
		 * Get list of all pages
		 * @returns {Promise}
		 */
		getPages() {
			if (!this.currentCollective) {
				return new Promise((resolve) => { resolve() })
			}
			return this.$store.dispatch('getPages')
				.catch(displayError('Could not fetch pages'))
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},

		openNav() {
			emit('toggle-navigation', { open: true })
		},

	},
}
</script>

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
