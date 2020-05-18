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
		<AppContent>
			<div v-if="currentPage">
				<div id="titleform">
					{{ t('wiki', 'Title') }}:
					<input ref="title"
						v-model="currentPage.newTitle"
						type="text"
						:disabled="updating || !savePossible"
						@blur="renamePage">
					<input v-model="edit"
						type="checkbox">
				</div>
				<PagePreview :page="currentPage" :preview="preview" :edit="edit"></PagePreview>
				<component :is="handler.component"
					v-show="edit && !preview"
					ref="editor"
					:key="'editor-' + currentPage.id"
					:fileid="currentPage.id"
					:basename="currentFilename"
					:filename="currentPath"
					:has-preview="true"
					:active="true"
					mime="text/markdown"
					class="file-view active"
					v-on:ready="hidePreview" />
			</div>
			<div v-else id="emptycontent">
				<div class="icon-file" />
				<h2>{{ t('wiki', 'Create a page to get started') }}</h2>
			</div>
		</AppContent>
	</div>
</template>

<script>
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'

import axios from '@nextcloud/axios'
import PagePreview from './PagePreview';

export default {
	name: 'App',
	components: {
		ActionButton,
		AppContent,
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
		PagePreview,
	},
	data: function() {
		return {
			pages: [],
			currentPageId: null,
			currentNewTitle: null,
			updating: false,
			loading: true,
			edit: false,
			preview: true,
		}
	},
	computed: {
		currentFilename() {
			return `${this.currentPage.title}.md`
		},

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
		currentPath() {
			return `/Wiki/${this.currentFilename}`
		},
		handler() {
			return OCA.Viewer.availableHandlers.filter(h => h.mimes.indexOf('text/markdown') !== -1)[0]
		},

		/**
		 * Returns true if a page is selected and its title is not empty
		 * @returns {Boolean}
		 */
		savePossible() {
			return this.currentPage && this.currentPage.title !== ''
		},

	},

	watch: {
		'currentPage.title': function(val, oldVal) {
			if (!this.currentPage.newTitle) {
				this.currentPage.newTitle = val
			}
			document.title = this.currentPage.title + ' - Wiki - Nextcloud'
		},
	},
	/**
	 * Fetch list of pages when the component is loaded
	 */
	async mounted() {
		try {
			const response = await axios.get(OC.generateUrl('/apps/wiki/pages'))
			this.pages = response.data
		} catch (e) {
			console.error(e)
			OCP.Toast.error(t('wiki', 'Could not fetch pages'))
		}
		this.loading = false
	},

	methods: {
		/**
		 * Create a new page and focus the page content field automatically
		 * @param {Object} page Page object
		 */
		openPage(page) {
			this.preview = true
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
				const response = await axios.post(OC.generateUrl(`/apps/wiki/pages`), page)
				this.pages.push(response.data)
				this.currentPageId = response.data.id
				// Update title as it might have changed due to filename conflict handling
				this.currentPage.title = response.data.title

			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('wiki', 'Could not create the page'))
			}
			this.updating = false
		},
		/**
		 * Rename a page on the server
		 * @param {Object} page Page object
		 */
		async renamePage() {
			if (this.currentPage.title === this.currentPage.newTitle) {
				return
			}
			const page = this.currentPage
			this.updating = true
			try {
				page.title = page.newTitle
				delete page.newTitle
				const response = await axios.put(OC.generateUrl(`/apps/wiki/pages/${page.id}`), page)
				// Update title as it might have changed due to filename conflict handling
				page.title = response.data.title
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('wiki', 'Could not rename the page'))
			}
			this.updating = false
		},
		/**
		 * Delete a page, remove it from the frontend and show a hint
		 * @param {Object} page Page object
		 */
		async deletePage(page) {
			try {
				await axios.delete(OC.generateUrl(`/apps/wiki/pages/${page.id}`))
				this.pages.splice(this.pages.indexOf(page), 1)
				if (this.currentPageId === page.id) {
					this.currentPageId = null
				}
				OCP.Toast.success(t('wiki', 'Page deleted'))
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('wiki', 'Could not delete the page'))
			}
		},
		hidePreview() {
			this.preview = false
		},
	},
}
</script>
<style scoped>
	#app-content > div {
		width: 100%;
		height: 100%;
		padding: 20px;
		display: flex;
		flex-direction: column;
		flex-grow: 1;
	}

	#titleform > input[type="text"] {
		width: 80%;
		max-width: 670px;
	}
</style>
