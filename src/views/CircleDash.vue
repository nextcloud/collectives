<template>
	<Content app-name="wiki" :class="{'icon-loading': loading}">
		<Nav v-if="!selectedWiki"
			:loading="loading"
			:wikis="wikis"
			:selected-wiki="selectedWiki"
			@new="newPage" />
		<AppContent>
			<Breadcrumbs>
				<Breadcrumb title="Home"
					to="/"
					:primary="true"
					:force-menu="!selectedWiki"
					:open="!loading && wikis.length === 0">
					<ActionInput v-if="!selectedWiki"
						ref="newWikiName"
						icon="icon-add"
						@submit="newWiki">
						Name for a new wiki
					</ActionInput>
				</Breadcrumb>
				<Breadcrumb v-if="selectedWiki"
					:title="selectedWiki"
					:to="`/${selectedWiki}`">
					<ActionButton icon="icon-add" @click="newPage">
						{{ t('wiki', 'Add a page') }}
					</ActionButton>
				</Breadcrumb>
				<Breadcrumb v-if="currentPage"
					:title="currentPage.title"
					:to="`/${selectedWiki}/${currentPage.title}`">
					<ActionButton
						icon="icon-edit"
						:close-after-click="true"
						@click="edit = !edit">
						{{ t('wiki', 'Toggle edit mode') }}
					</ActionButton>
					<ActionButton
						icon="icon-delete"
						@click="deletePage">
						{{ t('wiki', 'Delete page') }}
					</ActionButton>
					<ActionButton icon="icon-menu" @click="showSidebar=!showSidebar">
						{{ t('wiki', 'Toggle sidebar') }}
					</ActionButton>
				</Breadcrumb>
			</Breadcrumbs>
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
						@emptyPreview="emptyPreview"
						@renamePage="renamePage" />
				</AppContentDetails>
			</div>
			<div v-else id="emptycontent">
				<div class="icon-add" />
				<h2>{{ t('wiki', 'Create a wiki...') }}</h2>
			</div>
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
import { showSuccess, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import ActionInput from '@nextcloud/vue/dist/Components/ActionInput'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Breadcrumbs from '@nextcloud/vue/dist/Components/Breadcrumbs'
import Breadcrumb from '@nextcloud/vue/dist/Components/Breadcrumb'
import Content from '@nextcloud/vue/dist/Components/Content'
import Nav from '../components/Nav'
import PagesList from '../components/PagesList'
import Page from '../components/Page'
import PageSidebar from '../components/PageSidebar'
import Version from '../components/Version'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'CircleDash',

	components: {
		AppContent,
		AppContentDetails,
		ActionInput,
		ActionButton,
		Breadcrumbs,
		Breadcrumb,
		Content,
		Nav,
		Page,
		PagesList,
		PageSidebar,
		Version,
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
		 * Return the currently selected page object
		 * @returns {Object|null}
		 */
		currentPage() {
			if (this.pageId === null) {
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

		wikiName() {
			// Somehow ActionInput.value does not reflect
			// the value of the input field.
			// This is a workaround.
			// TODO: properly fix this in nextclouds ActionInput.
			return this.$refs.newWikiName.$refs.form[1].value
		},

	},

	watch: {
		'selectedPage': function() {
			this.setCurrentVersion(null)
			this.editToggle = EditState.Unset
		},
	},

	mounted() {
		this.getPages()
		this.getWikis()
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
				const response = await axios.post(generateUrl(`/apps/wiki/_pages`), page)
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
		 */
		async newWiki() {
			const wiki = {
				name: this.wikiName,
			}
			const response = await axios.post(generateUrl(`/apps/wiki/_wikis`), wiki)
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
				const response = await axios.put(generateUrl(`/apps/wiki/_pages/${page.id}`), page)
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
				await axios.delete(generateUrl(`/apps/wiki/_pages/${pageId}`))
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

		/**
		 * Called when an empty preview was loaded.
		 * Switch to edit mode
		 */
		emptyPreview() {
			this.edit = true
		},
	},
}
</script>
