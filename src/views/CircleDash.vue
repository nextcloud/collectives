<template>
	<Content app-name="wiki" :class="{'icon-loading': loading}">
		<Nav :loading="loading"
			:wikis="wikis" />
		<AppContent>
			<WikiHeading v-if="currentWiki"
				:wiki="currentWiki"
				@newPage="newPage" />
			<TopBar v-if="currentPage"
				:edit="edit"
				:sidebar="showSidebar"
				@toggleSidebar="showSidebar = !showSidebar" />
			<div v-if="selectedWiki" id="app-content-wrapper">
				<PagesList
					:show-details="!!currentPage"
					:loading="loading"
					:pages="pages"
					:current-page="currentPage" />
				<AppContentDetails v-if="currentPage">
					<Version v-if="currentVersion"
						:page="currentPage"
						:version="currentVersion"
						:current-version-timestamp="currentVersionTimestamp"
						:updating="updating"
						@toggleSidebar="showSidebar=!showSidebar"
						@resetVersion="resetVersion" />
					<Page key="selectedPage"
						:page="currentPage"
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
						<ActionInput v-if="!selectedWiki"
							ref="newWikiName"
							icon="icon-star"
							@submit="newWiki">
							Name for a new wiki
						</ActionInput>
					</ul>
				</template>
			</EmptyContent>
		</AppContent>
		<PageSidebar v-if="currentPage"
			v-show="showSidebar"
			:page="currentPage"
			:current-version-timestamp="currentVersionTimestamp"
			@preview-version="setCurrentVersion"
			@close="showSidebar=false" />
	</Content>
</template>

<script>
import axios from '@nextcloud/axios'
import { emit } from '@nextcloud/event-bus'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
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

	props: {
		selectedWiki: {
			type: String,
			required: false,
			default: null,
		},
		selectedPage: {
			type: String,
			required: false,
			default: null,
		},
	},

	data: function() {
		return {
			pages: [],
			currentVersion: null,
			loading: true,
			updating: false,
			showSidebar: false,
			editToggle: EditState.Unset,
			currentVersionTimestamp: 0,
			wikis: [],
		}
	},

	computed: {
		/**
		 * Return the currently selected wiki
		 * @returns {Object|null}
		 */
		currentWiki() {
			if (this.selectedWiki === null) {
				return null
			}
			return this.wikis.find((wiki) => wiki.folderName === this.selectedWiki)
		},

		/**
		 * Return the currently selected page object
		 * @returns {Object|null}
		 */
		currentPage() {
			if (this.selectedPage === null) {
				return null
			}
			return this.pages.find((page) => page.title === this.selectedPage)
		},

		edit: {
			get: function() {
				return this.editToggle === EditState.Edit
			},
			set: function(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},

		pagesUrl() {
			return generateUrl(`/apps/wiki/_wikis/${this.currentWiki.id}/_pages`)
		},
	},

	watch: {
		'currentWiki': function() {
			if (this.currentWiki) {
				this.getPages()
				this.closeNav()
			}
		},

		'selectedPage': function() {
			this.setCurrentVersion(null)
			this.editToggle = EditState.Unset
		},
	},

	mounted() {
		this.getWikis()
	},

	methods: {

		/**
		 * Get list of all pages
		 */
		async getPages() {
			if (!this.currentWiki) {
				return
			}
			this.loading = true
			try {
				const response = await axios.get(this.pagesUrl)
				// sort pages by timestamp
				this.pages = response.data.sort((a, b) => b.timestamp - a.timestamp)
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not fetch pages'))
			}
			this.loading = false
		},

		/**
		 * Fetch and update one particular page
		 * @param {number} pageId Page ID
		 */
		async getPage(pageId) {
			if (!this.currentWiki) {
				return
			}
			this.loading = true
			try {
				const response = await axios.get(this.pageUrl(pageId))
				// update page object from the list of pages
				this.pages.splice(this.pages.findIndex(page => page.id === response.data.id), 1, response.data)
			} catch (e) {
				console.error(e)
				showError(t('wiki', `Could not fetch page ${pageId}`))
			}
			this.loading = false
		},

		/**
		 * Get list of all pages
		 */
		async getWikis() {
			this.loading = true
			const view = this
			const response = await axios.get(generateUrl(`/apps/wiki/_wikis`))
			view.wikis = response.data
			view.loading = false
		},

		/**
		 * Create a new page and focus the page content field automatically
		 */
		async newPage() {
			const page = {
				title: 'New Page',
			}
			this.updating = true
			try {
				const response = await axios.post(this.pagesUrl, page)
				// Add new page to the beginning of pages array
				this.pages.unshift({ newTitle: '', ...response.data })
				this.$router.push(`/${this.selectedWiki}/${response.data.title}?fileId=${response.data.id}`)
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
			const response = await axios.post(generateUrl(`/apps/wiki/_wikis`), wiki)
			await this.getWikis()
			this.$router.push(`/${response.data.folderName}`)
		},

		/**
		 * Rename currentPage on the server
		 * @param {string} newTitle New title for the page
		 */
		async renamePage(newTitle) {
			if (this.currentPage.title === newTitle) {
				return
			}
			const page = this.currentPage
			this.updating = true
			try {
				page.title = newTitle
				delete page.newTitle
				const response = await axios.put(this.pageUrl(page.id), page)
				// Update title as it might have changed due to filename conflict handling
				// also update all other attributes such as filename etc.
				Object.assign(page, response.data)
				this.$router.push(`/${this.selectedWiki}/${response.data.title}?fileId=${response.data.id}`)
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not rename the page'))
			}
			this.updating = false
		},

		/**
		 * Delete the current page,
		 * remove it from the frontend and show a hint
		 */
		async deletePage() {
			try {
				const pageId = this.currentPage.id
				await axios.delete(this.pageUrl(pageId))
				this.pages.splice(this.pages.findIndex(page => page.id === pageId), 1)
				this.$router.push(`/${this.selectedWiki}`)
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
			this.getPage(this.pageId)
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

		pageUrl(pageId) {
			return `${this.pagesUrl}/${pageId}`
		},

		closeNav() {
			emit('toggle-navigation', { open: false })
		},
	},
}
</script>
