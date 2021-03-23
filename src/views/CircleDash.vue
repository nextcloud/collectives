<template>
	<Content app-name="collective">
		<!-- go back to list when in details mode -->
		<a v-if="showDetails && isMobile"
			class="app-details-toggle icon-confirm"
			href="#"
			@click.stop.prevent="showList" />
		<Nav @newCollective="newCollective" @deleteCollective="deleteCollective" />
		<AppContent>
			<CollectiveHeading v-if="currentCollective"
				@toggleDetails="showDetails = true" />
			<Collective v-if="collectiveParam"
				:current-version="currentVersion"
				:current-version-timestamp="currentVersionTimestamp"
				:show-details="showDetails"
				@preview-version="setCurrentVersion"
				@resetVersion="resetVersion"
				@showVersions="showSidebar = true"
				@toggleDetails="showDetails = true"
				@toggleSidebar="showSidebar=!showSidebar" />
			<EmptyContent v-else icon="icon-ant">
				{{ t('collectives', 'No collective selected') }}
				<template #desc>
					{{ t('collectives', 'Select a collective or create a new one on the left.') }}
				</template>
			</EmptyContent>
		</AppContent>
		<PageSidebar v-if="currentPage"
			v-show="showSidebar"
			:current-version-timestamp="currentVersionTimestamp"
			@preview-version="setCurrentVersion"
			@close="showSidebar=false" />
	</Content>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Content from '@nextcloud/vue/dist/Components/Content'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Collective from '../components/Collective'
import CollectiveHeading from '../components/CollectiveHeading'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import Nav from '../components/Nav'
import PageSidebar from '../components/PageSidebar'

export default {
	name: 'CircleDash',

	components: {
		AppContent,
		Content,
		Collective,
		CollectiveHeading,
		EmptyContent,
		Nav,
		PageSidebar,
	},

	mixins: [
		isMobile,
	],

	data() {
		return {
			currentVersion: null,
			showSidebar: false,
			showDetails: true,
			currentVersionTimestamp: 0,
		}
	},

	computed: {

		/**
		 * Return the url param for the currently selected collective
		 * @returns {String|undefined}
		 */
		collectiveParam() {
			return this.$store.getters.collectiveParam
		},

		/**
		 * Return the currently selected collective
		 * @returns {Object|undefined}
		 */
		currentCollective() {
			return this.$store.getters.currentCollective
		},

		/**
		 * Return the url param for the currently selected page
		 * @returns {String|undefined}
		 */
		pageParam() {
			return this.$store.getters.pageParam
		},

		/**
		 * Return the currently selected page object
		 * @returns {Object|undefined}
		 */
		currentPage() {
			return this.$store.getters.currentPage
		},
	},

	watch: {
		'collectiveParam'() {
			if (this.currentCollective) {
				this.getPages()
				this.closeNav()
			}
		},
		'pageParam'() {
			this.setCurrentVersion(null)
		},
	},

	async mounted() {
		await this.getCollectives()
		if (this.currentCollective) {
			this.getPages()
			this.closeNav()
		}
	},

	methods: {

		/**
		 * Get list of all collectives
		 */
		async getCollectives() {
			try {
				await this.$store.dispatch('getCollectives')
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not fetch collectives'))
			}
		},

		/**
		 * Get list of all pages
		 */
		async getPages() {
			if (!this.currentCollective) {
				return
			}
			try {
				await this.$store.dispatch('getPages')
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not fetch pages'))
			}
		},

		/**
		 * Create a new collective with the name given in the breadcrumb input
		 * @param {Object} collective Properties of the new collective
		 */
		async newCollective(collective) {
			try {
				await this.$store.dispatch('newCollective', collective)
				this.$router.push(this.$store.getters.updatedCollectivePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the collective'))
			}
		},

		/**
		 * Delete a collective with the given name
		 * @param {Object} collective Properties of the collective
		 */
		async deleteCollective(collective) {
			try {
				await this.$store.dispatch('deleteCollective', collective)
				if (this.$store.getters.collectiveParam === collective.name) {
					this.$router.push('/')
				}
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the collective'))
			}
		},

		/**
		 * Reset the version
		 */
		resetVersion() {
			this.setCurrentVersion(null)
		},

		/**
		 * Set specific version of currentPage (passed to Page component)
		 * @param {object} version Page version object
		 */
		setCurrentVersion(version) {
			this.currentVersion = version
			this.currentVersionTimestamp = (version ? version.timestamp : 0)
		},

		showList() {
			this.showDetails = false
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
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
	transform: rotate(180deg);
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
