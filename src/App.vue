<template>
	<div id="content" class="app-wiki">
		<AppNavigation>
			<AppNavigationNew v-if="!loading"
				:text="t('wiki', 'New page')"
				:disabled="false"
				button-id="new-wiki-button"
				button-class="icon-add"
				@click="newPage" />
			<ul>
				<AppNavigationItem v-for="page in pages"
					:key="page.id"
					:title="page.title ? page.title : t('wiki', 'New page')"
					:class="{active: currentPageId === page.id}"
					@click="openPage(page)" />
			</ul>
		</AppNavigation>
		<Version v-if="currentPage && currentVersion"
			:page="currentPage"
			:version="currentVersion"
			:current-version-timestamp="currentVersionTimestamp"
			:updating="updating"
			@toggleSidebar="showSidebar=!showSidebar"
			@resetVersion="resetVersion" />
		<Page v-else-if="currentPage"
			:page="currentPage"
			:updating="updating"
			@toggleSidebar="showSidebar=!showSidebar"
			@renamePage="renamePage"
			@deletePage="deletePage" />
		<Start v-else />
		<PageSidebar v-if="currentPage"
			v-show="showSidebar"
			:page="currentPage"
			:current-version-timestamp="currentVersionTimestamp"
			@preview-version="setCurrentVersion"
			@close="showSidebar=false" />
	</div>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'

import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import Page from './components/Page'
import Version from './components/Version'
import PageSidebar from './components/PageSidebar'
import Start from './components/Start'

export default {
	name: 'App',

	components: {
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		Page,
		PageSidebar,
		Start,
		Version,
	},

	data: function() {
		return {
			pages: [],
			currentPageId: null,
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
			if (this.currentPageId === null) {
				return null
			}
			return this.pages.find((page) => page.id === this.currentPageId)
		},
	},

	watch: {
		'currentPageId': function() {
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
		 * @param {object} page Page object
		 */
		openPage(page) {
			this.currentPageId = page.id
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
				this.currentPageId = response.data.id
				// Update title as it might have changed due to filename conflict handling
				this.currentPage.title = response.data.title

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
				if (this.currentPageId === pageId) {
					this.currentPageId = null
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
			this.getPage(this.currentPageId)
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

<style>
	#app-content > div {
		width: 100%;
		height: 100%;
		padding: 20px;
		display: flex;
		flex-direction: column;
		flex-grow: 1;
	}

	.page-title, #titleform input[type="text"] {
		font-size: 36px;
		border: none;
		font-weight: 600;
		color: var(--color-main-text);
		width: 100%
	}

	#action-menu {
		position: absolute;
		right: 0;
	}
</style>
