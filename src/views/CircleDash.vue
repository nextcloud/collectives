<template>
	<Content app-name="collective" :class="{'icon-loading': loading}">
		<Nav @newCollective="newCollective" />
		<AppContent>
			<CollectiveHeading v-if="currentCollective" />
			<div v-if="collectiveParam" id="app-content-wrapper">
				<PagesList
					@newPage="newPage" />
				<AppContentDetails v-if="currentPage">
					<Version v-if="currentVersion"
						:page="currentPage"
						:version="currentVersion"
						:current-version-timestamp="currentVersionTimestamp"
						:updating="updating"
						@toggleSidebar="showSidebar=!showSidebar"
						@preview-version="setCurrentVersion"
						@resetVersion="resetVersion" />
					<Page v-else
						key="currentPage.timestamp"
						:updating="updating"
						:edit="edit"
						@deletePage="deletePage"
						@edit="edit = true"
						@toggleEdit="edit = !edit"
						@showVersions="showSidebar = true"
						@renamePage="renamePage" />
				</AppContentDetails>
			</div>
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
import { showSuccess, showError } from '@nextcloud/dialogs'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import Content from '@nextcloud/vue/dist/Components/Content'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Nav from '../components/Nav'
import PagesList from '../components/PagesList'
import Page from '../components/Page'
import PageSidebar from '../components/PageSidebar'
import Version from '../components/Version'
import CollectiveHeading from '../components/CollectiveHeading'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'CircleDash',

	components: {
		AppContent,
		AppContentDetails,
		Content,
		EmptyContent,
		Nav,
		Page,
		PagesList,
		PageSidebar,
		Version,
		CollectiveHeading,
	},

	data: function() {
		return {
			currentVersion: null,
			updating: false,
			showSidebar: false,
			editToggle: EditState.Unset,
			currentVersionTimestamp: 0,
		}
	},

	computed: {
		/**
		 * Return the currently selected collective
		 * @returns {Object|undefined}
		 */
		currentCollective() {
			return this.$store.getters.currentCollective
		},

		/**
		 * Return the url param for the currently selected collective
		 * @returns {String|undefined}
		 */
		collectiveParam() {
			return this.$store.getters.collectiveParam
		},

		/**
		 * Return the currently selected page object
		 * @returns {Object|undefined}
		 */
		currentPage() {
			return this.$store.getters.currentPage
		},

		/**
		 * Return the url param for the currently selected page
		 * @returns {String|undefined}
		 */
		pageParam() {
			return this.$store.getters.pageParam
		},

		edit: {
			get: function() {
				return this.editToggle === EditState.Edit
			},
			set: function(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},

		loading: {
			get: function() {
				return this.$store.state.loading
			},
			set: function(val) {
				this.$store.commit(val ? 'loading' : 'done')
			},
		},

		pages() {
			return this.$store.state.pages
		},

		collectives() {
			return this.$store.state.collectives
		},
	},

	watch: {
		'collectiveParam': function() {
			if (this.currentCollective) {
				this.getPages()
				this.closeNav()
			}
		},

		'pageParam': function() {
			this.setCurrentVersion(null)
			this.editToggle = EditState.Unset
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
		 * Fetch and update one particular page
		 * @param {number} pageId Page ID
		 */
		async getPage(pageId) {
			if (!this.currentCollective) {
				return
			}
			try {
				await this.$store.dispatch('getPage', pageId)
			} catch (e) {
				console.error(e)
				showError(t('collectives', `Could not fetch page ${pageId}`))
			}
		},

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
		 * Create a new page and focus the page  automatically
		 */
		async newPage() {
			const page = {
				title: t('collectives', 'New Page'),
			}
			try {
				await this.$store.dispatch('newPage', page)
				this.$router.push(this.$store.getters.updatedPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
			this.updating = false
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
		 * Rename currentPage on the server
		 * @param {string} newTitle New title for the page
		 */
		async renamePage(newTitle) {
			if (this.currentPage.title === newTitle) {
				return
			}
			try {
				await this.$store.dispatch('renamePage', newTitle)
				this.$router.push(this.$store.getters.updatedPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not rename the page'))
			}
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 */
		async deletePage() {
			try {
				await this.$store.dispatch('deletePage')
				this.$router.push(`/${this.collectiveParam}`)
				showSuccess(t('collectives', 'Page deleted'))
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not delete the page'))
			}
		},

		/**
		 * Reset the version and reload current page in order to update page properties
		 */
		resetVersion() {
			this.getPage(this.currentPage.id)
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

		closeNav() {
			emit('toggle-navigation', { open: false })
		},
	},
}
</script>
