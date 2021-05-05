<template>
	<div id="app-content-wrapper">
		<PagesList @newPage="newPage" />
		<AppContentDetails v-if="currentPage">
			<Version v-if="version" :page="currentPage" />
			<Page v-else
				key="currentPage.timestamp"
				:edit="edit"
				@edit="edit = true"
				@toggleEdit="edit = !edit" />
		</AppContentDetails>
	</div>
</template>

<script>

import { showError } from '@nextcloud/dialogs'
import { mapGetters } from 'vuex'
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

	data() {
		return {
			editToggle: EditState.Unset,
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'currentPage',
			'pageParam',
			'version',
		]),

		edit: {
			get() {
				return this.editToggle === EditState.Edit
			},
			set(val) {
				this.editToggle = val ? EditState.Edit : EditState.Read
			},
		},
	},

	watch: {

		'pageParam'() {
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
	},
}
</script>
