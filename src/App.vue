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
						<ActionButton v-if="page.id === -1"
							icon="icon-close"
							@click="cancelNewPage(page)">
							{{ t('wiki', 'Cancel page creation') }}
						</ActionButton>
						<ActionButton v-else
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
				<input ref="title"
					v-model="currentPage.title"
					type="text"
					:disabled="updating">
				<textarea ref="content" v-model="currentPage.content" :disabled="updating" />
				<input type="button"
					class="primary"
					:value="t('wiki', 'Save')"
					:disabled="updating || !savePossible"
					@click="savePage">
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

export default {
	name: 'App',
	components: {
		ActionButton,
		AppContent,
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
	},
	data: function() {
		return {
			pages: [],
			currentPageId: null,
			updating: false,
			loading: true,
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

		/**
		 * Returns true if a page is selected and its title is not empty
		 * @returns {Boolean}
		 */
		savePossible() {
			return this.currentPage && this.currentPage.title !== ''
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
			if (this.updating) {
				return
			}
			this.currentPageId = page.id
			this.$nextTick(() => {
				this.$refs.content.focus()
			})
		},
		/**
		 * Action tiggered when clicking the save button
		 * create a new page or save
		 */
		savePage() {
			if (this.currentPageId === -1) {
				this.createPage(this.currentPage)
			} else {
				this.updatePage(this.currentPage)
			}
		},
		/**
		 * Create a new page and focus the page content field automatically
		 * The page is not yet saved, therefore an id of -1 is used until it
		 * has been persisted in the backend
		 */
		newPage() {
			if (this.currentPageId !== -1) {
				this.currentPageId = -1
				this.pages.push({
					id: -1,
					title: '',
					content: '',
				})
				this.$nextTick(() => {
					this.$refs.title.focus()
				})
			}
		},
		/**
		 * Abort creating a new page
		 */
		cancelNewPage() {
			this.pages.splice(this.pages.findIndex((page) => page.id === -1), 1)
			this.currentPageId = null
		},
		/**
		 * Create a new page by sending the information to the server
		 * @param {Object} page Page object
		 */
		async createPage(page) {
			this.updating = true
			try {
				const response = await axios.post(OC.generateUrl(`/apps/wiki/pages`), page)
				const index = this.pages.findIndex((match) => match.id === this.currentPageId)
				this.$set(this.pages, index, response.data)
				this.currentPageId = response.data.id
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('wiki', 'Could not create the page'))
			}
			this.updating = false
		},
		/**
		 * Update an existing page on the server
		 * @param {Object} page Page object
		 */
		async updatePage(page) {
			this.updating = true
			try {
				await axios.put(OC.generateUrl(`/apps/wiki/pages/${page.id}`), page)
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('wiki', 'Could not update the page'))
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
	input[type="text"] {
		width: 100%;
	}
	textarea {
		flex-grow: 1;
		width: 100%;
	}
</style>
