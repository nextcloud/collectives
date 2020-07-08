<template>
	<div id="content" class="app-wiki">
		<Nav :key="`nav-${pageId}`"
			:loading="loading"
			:pages="pages"
			:current-page-id="pageId"
			@new="newPage" />
		<Version v-if="currentPage && currentVersion"
			:page="currentPage"
			:version="currentVersion"
			:current-version-timestamp="currentVersionTimestamp"
			:updating="updating"
			@toggleSidebar="showSidebar=!showSidebar"
			@resetVersion="resetVersion" />
		<Page v-else-if="currentPage"
			:key="`page-${pageId}`"
			:page="currentPage"
			:updating="updating"
			@toggleSidebar="showSidebar=!showSidebar"
			@renamePage="renamePage"
			@deletePage="deletePage" />
		<PageSidebar v-if="currentPage"
			v-show="showSidebar"
			:page="currentPage"
			:current-version-timestamp="currentVersionTimestamp"
			@preview-version="setCurrentVersion"
			@close="showSidebar=false" />
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import Nav from '../components/Nav'
import Page from '../components/Page'
import Version from '../components/Version'
import PageSidebar from '../components/PageSidebar'

export default {
	name: 'Pages',

	components: {
		Nav,
		Page,
		PageSidebar,
		Version,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},

	data: function() {
		return {
			pages: [],
			currentVersion: null,
			updating: false,
			loading: true,
			showSidebar: false,
			currentVersionTimestamp: 0,
		}
	},

	computed: {
		/**
		 * Return the currently selected page object
		 * @returns {Object|null}
		 */
		currentPage() {
			if (this.pageId === null) {
				return null
			}
			return this.pages.find((page) => page.id === this.pageId)
		},
	},

	watch: {
		'pageId': function() {
			this.setCurrentVersion(null)
		},
	},

	mounted() {
		this.getPages()
	},

	methods: {
		/**
		 * Get list of all pages
		 */
		async getPages() {
			this.loading = true
			try {
				const response = await axios.get(generateUrl('/apps/wiki/_pages'))
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
			this.loading = true
			try {
				const response = await axios.get(generateUrl(`/apps/wiki/_pages/${pageId}`))
				// update page object from the list of pages
				this.pages.splice(this.pages.findIndex(page => page.id === response.data.id), 1, response.data)
			} catch (e) {
				console.error(e)
				showError(t('wiki', `Could not fetch page ${pageId}`))
			}
			this.loading = false
		},

		/**
		 * Create a new page and focus the page content field automatically
		 * The page is not yet saved, therefore an id of -1 is used until it
		 * has been persisted in the backend
		 */
		newPage() {
			const page = {
				title: 'New Page',
			}
			this.createPage(page)
		},

		/**
		 * Create a new page by sending the information to the server
		 * @param {object} page Page object
		 */
		async createPage(page) {
			this.updating = true
			try {
				const response = await axios.post(generateUrl(`/apps/wiki/_pages`), page)
				// Add new page to the beginning of pages array
				this.pages.unshift({ newTitle: '', ...response.data })
				this.$router.push(`/${response.data.title}.md?fileId=${response.data.id}`)
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not create the page'))
			}
			this.updating = false
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
				const response = await axios.put(generateUrl(`/apps/wiki/_pages/${page.id}`), page)
				// Update title as it might have changed due to filename conflict handling
				// also update all other attributes such as filename etc.
				Object.assign(page, response.data)
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not rename the page'))
			}
			this.updating = false
		},

		/**
		 * Delete a page, remove it from the frontend and show a hint
		 * @param {number} pageId Page ID
		 */
		async deletePage(pageId) {
			try {
				await axios.delete(generateUrl(`/apps/wiki/_pages/${pageId}`))
				this.pages.splice(this.pages.findIndex(page => page.id === pageId), 1)
				if (this.pageId === pageId) {
					this.pageId = null
				}
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
	},
}
</script>
