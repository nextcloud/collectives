<template>
	<div id="app-content-wrapper">
		<PagesList
			@newPage="newPage" />
		<AppContentDetails v-if="currentPage && !$store.state.loading.collective">
			<Version v-if="currentVersion"
				:page="currentPage"
				:version="currentVersion"
				:current-version-timestamp="currentVersionTimestamp"
				@toggleSidebar="$emit('toggleSidebar')"
				@showCurrent="$emit('preview-version', null)"
				@resetVersion="resetVersion" />
			<Page v-else
				key="currentPage.timestamp"
				:edit="edit"
				@deletePage="deletePage"
				@edit="edit = true"
				@toggleEdit="edit = !edit"
				@showVersions="$emit('showVersions')"
				@renamePage="renamePage" />
		</AppContentDetails>
	</div>
</template>

<script>

import { showSuccess, showError } from '@nextcloud/dialogs'
import AppContentDetails from '@nextcloud/vue/dist/Components/AppContentDetails'
import Page from '../components/Page'
import PagesList from '../components/PagesList'
import Version from '../components/Version'

const EditState = { Unset: 0, Edit: 1, Read: 2 }

export default {
	name: 'Collective',

	components: {
		AppContentDetails,
		Page,
		PagesList,
		Version,
	},

	props: {
		currentVersion: {
			type: Object,
			default: null,
		},
		currentVersionTimestamp: {
			type: Number,
			required: true,
		},
	},

	data: function() {
		return {
			editToggle: EditState.Unset,
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

		edit: {
			get: function() {
				return this.editToggle === EditState.Edit
			},
			set: function(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},
	},

	watch: {

		'pageParam': function() {
			this.editToggle = EditState.Unset
		},
	},

	methods: {

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
		 * reload current page in order to update page properties and reset the version
		 */
		resetVersion() {
			this.getPage(this.currentPage.id)
			this.$emit('resetVersion')
		},
	},
}
</script>
