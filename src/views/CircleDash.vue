<template>
	<Content app-name="wiki" :class="{'icon-loading': loading}">
		<Nav />
		<AppContent>
			<WikiHeading v-if="currentWiki"
				@newPage="newPage" />
			<TopBar v-if="currentPage"
				:edit="edit"
				:sidebar="showSidebar"
				@toggleSidebar="showSidebar = !showSidebar" />
			<div v-if="wikiParam" id="app-content-wrapper">
				<PagesList />
				<AppContentDetails v-if="currentPage">
					<Version v-if="currentVersion"
						:page="currentPage"
						:version="currentVersion"
						:current-version-timestamp="currentVersionTimestamp"
						:updating="updating"
						@toggleSidebar="showSidebar=!showSidebar"
						@resetVersion="resetVersion" />
					<Page key="currentPage.timestamp"
						:updating="updating"
						:edit="edit"
						@deletePage="deletePage"
						@edit="edit = true"
						@toggleEdit="edit = !edit"
						@renamePage="renamePage" />
				</AppContentDetails>
			</div>
			<EmptyContent v-else icon="icon-star">
				{{ t('wiki', 'No wiki selected') }}
				<template #desc>
					{{ t('wiki', 'Select a wiki on the left or create a new one:') }}
					<ul>
						<ActionInput v-if="!wikiParam"
							ref="newWikiName"
							icon="icon-star"
							@submit="newWiki">
							{{ t('wiki', 'Name for a new wiki') }}
						</ActionInput>
					</ul>
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
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import Content from '@nextcloud/vue/dist/Components/Content'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import Nav from '../components/Nav'
import PagesList from '../components/PagesList'
import Page from '../components/Page'
import PageSidebar from '../components/PageSidebar'
import TopBar from '../components/TopBar'
import Version from '../components/Version'
import WikiHeading from '../components/WikiHeading'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'CircleDash',

	components: {
		AppContent,
		AppContentDetails,
		ActionInput,
		Content,
		EmptyContent,
		Nav,
		Page,
		PagesList,
		PageSidebar,
		TopBar,
		Version,
		WikiHeading,
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
		 * Return the currently selected wiki
		 * @returns {Object|undefined}
		 */
		currentWiki() {
			return this.$store.getters.currentWiki
		},

		/**
		 * Return the url param for the currently selected wiki
		 * @returns {String|undefined}
		 */
		wikiParam() {
			return this.$store.getters.wikiParam
		},

		/**
		 * Return the currently selected page object
		 * @returns {Object|undefined}
		 */
		currentPage() {
			return this.$store.getters.currentPage
		},

		/**
		 * Return the url param for the currently selected wiki
		 * @returns {String|undefined}
		 */
		pageParam() {
			return this.$store.getters.wikiParam
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

		wikis() {
			return this.$store.state.wikis
		},
	},

	watch: {
		'wikiParam': function() {
			if (this.currentWiki) {
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
		await this.getWikis()
		if (this.currentWiki) {
			this.getPages()
			this.closeNav()
		}
	},

	methods: {

		/**
		 * Get list of all pages
		 */
		async getPages() {
			if (!this.currentWiki) {
				return
			}
			try {
				await this.$store.dispatch('getPages')
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not fetch pages'))
			}
		},

		/**
		 * Fetch and update one particular page
		 * @param {number} pageId Page ID
		 */
		async getPage(pageId) {
			if (!this.currentWiki) {
				return
			}
			try {
				await this.$store.dispatch('getPage', pageId)
			} catch (e) {
				console.error(e)
				showError(t('wiki', `Could not fetch page ${pageId}`))
			}
		},

		/**
		 * Get list of all wikis
		 */
		async getWikis() {
			try {
				await this.$store.dispatch('getWikis')
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not fetch wikis'))
			}
		},

		/**
		 * Create a new page and focus the page  automatically
		 */
		async newPage() {
			try {
				await this.$store.dispatch('newPage')
				this.$router.push(this.$store.getters.updatedPagePath)
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not create the page'))
			}
			this.updating = false
		},

		/**
		 * Create a new wiki with the name given in the breadcrumb input
		 * @param {Event} event Event that triggered the function
		 */
		async newWiki(event) {
			const name = event.currentTarget[1].value
			const wiki = { name }
			try {
				await this.$store.dispatch('newWiki', wiki)
				this.$router.push(this.$store.getters.updatedWikiPath)
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not create the wiki'))
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
				showError(t('wiki', 'Could not rename the page'))
			}
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 */
		async deletePage() {
			try {
				await this.$store.dispatch('deletePage')
				this.$router.push(`/${this.wikiParam}`)
				showSuccess(t('wiki', 'Page deleted'))
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not delete the page'))
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
