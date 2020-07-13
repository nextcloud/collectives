<template>
	<div id="content" class="app-wiki">
		<Nav :loading="loading"
			:pages="pages"
			:current-page-id="null"
			@new="newPage" />
		<AppContent>
			<div id="emptycontent">
				<div class="icon-file" />
				<h2>{{ t('wiki', 'Create a wiki for one of your circles to get started') }}</h2>

				<ul>
					<li v-for="circle in circles" :key="circle.name">
						<router-link :to="`/circles/${circle.unique_id}`">
							{{ circle.name }}
						</router-link>
					</li>
				</ul>
			</div>
		</AppContent>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Nav from '../components/Nav'
export default {
	name: 'Start',

	components: {
		AppContent,
		Nav,
	},

	data: function() {
		return {
			pages: [],
			loading: false,
			updating: false,
			circles: [],
		}
	},

	mounted() {
		this.getPages()
		this.getCircles()
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
		 * Get list of all pages
		 */
		getCircles() {
			this.loading = true
			const view = this
			OCA.Circles.api.listCircles('all', '', 0, function(response) {
				view.circles = response.data
				view.loading = false
			})
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

	},
}
</script>
