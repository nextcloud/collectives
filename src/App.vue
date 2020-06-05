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
					@click="openPage(page)">
					<template slot="actions">
						<ActionButton
							icon="icon-delete"
							@click="deletePage(page)">
							{{ t('wiki', 'Delete page') }}
						</ActionButton>
					</template>
				</AppNavigationItem>
			</ul>
		</AppNavigation>
		<Page v-if="currentPage"
			:page="currentPage"
			:updating="updating"
			@toggleSidebar="showSidebar=!showSidebar"
			@rename-page="renamePage" />
		<Start v-else />
		<PageSidebar v-if="currentPage"
			v-show="showSidebar"
			:page="currentPage"
			@close="showSidebar=false" />
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'

import axios from '@nextcloud/axios'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import Page from './components/Page'
import PageSidebar from './components/PageSidebar'
import Start from './components/Start'

export default {
	name: 'App',
	components: {
		ActionButton,
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		Page,
		PageSidebar,
		Start,
	},

	data: function() {
		return {
			pages: [],
			currentPageId: null,
			updating: false,
			loading: true,
			showSidebar: false,
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

	/**
	 * Fetch list of pages when the component is loaded
	 */
	async mounted() {
		try {
			const response = await axios.get(generateUrl('/apps/wiki/pages'))
			this.pages = response.data
		} catch (e) {
			console.error(e)
			showError(t('wiki', 'Could not fetch pages'))
		}
		this.loading = false
	},

	methods: {
		/**
		 * Create a new page and focus the page content field automatically
		 * @param {Object} page Page object
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
		 * @param {Object} page Page object
		 */
		async createPage(page) {
			this.updating = true
			try {
				const response = await axios.post(generateUrl(`/apps/wiki/pages`), page)
				this.pages.push(response.data)
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
		 * @param {String} newTitle New title for the page
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
				const response = await axios.put(generateUrl(`/apps/wiki/pages/${page.id}`), page)
				// Update title as it might have changed due to filename conflict handling
				page.title = response.data.title
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not rename the page'))
			}
			this.updating = false
		},

		/**
		 * Delete a page, remove it from the frontend and show a hint
		 * @param {Object} page Page object
		 */
		async deletePage(page) {
			try {
				await axios.delete(generateUrl(`/apps/wiki/pages/${page.id}`))
				this.pages.splice(this.pages.indexOf(page), 1)
				if (this.currentPageId === page.id) {
					this.currentPageId = null
				}
				showSuccess(t('wiki', 'Page deleted'))
			} catch (e) {
				console.error(e)
				showError(t('wiki', 'Could not delete the page'))
			}
		},

	},
}
</script>
