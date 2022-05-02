<template>
	<div>
		<div v-for="page in pagesTreeWalk()" :key="page.id">
			<PagePrint :page="page"
				@loading="waitingFor.push(page.id)"
				@ready="ready(page.id)" />
		</div>
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import PagePrint from './PagePrint'
import { GET_PAGES } from '../store/actions'
import displayError from '../util/displayError'

export default {
	name: 'CollectivePrint',

	components: {
		PagePrint,
	},

	data() {
		return {
			waitingFor: [],
		}
	},

	computed: {
		...mapGetters([
			'pagesTreeWalk',
		]),
	},

	mounted() {
		this.getPages()
	},

	methods: {
		...mapActions({
			dispatchGetPages: GET_PAGES,
		}),

		/**
		 * Get list of all pages
		 */
		async getPages() {
			await this.dispatchGetPages()
				.catch(displayError('Could not fetch pages'))
		},

		ready(pageId) {
			if (this.waitingFor.indexOf(pageId) >= 0) {
				this.waitingFor.splice(this.waitingFor.indexOf(pageId), 1)
			}
			if (!this.waitingFor.length) {
				// Scroll back to the beginning of the document
				document.getElementById('page-title-collective').scrollIntoView()
				window.print()
			}
		},
	},
}
</script>
