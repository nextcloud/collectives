<template>
	<div>
		<input id="sharingToken"
			type="hidden"
			name="sharingToken"
			:value="shareTokenParam">
		<EmptyContent v-show="loading" icon="icon-loading">
			<h1>{{ t('collectives', 'Preparing collective print') }}</h1>
		</EmptyContent>
		<div v-for="page in pagesTreeWalk()" v-show="!loading" :key="page.id">
			<PagePrint :page="page"
				@loading="waitingFor.push(page.id)"
				@ready="ready(page.id)" />
		</div>
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import PagePrint from './PagePrint'
import { GET_PAGES } from '../store/actions'
import displayError from '../util/displayError'

export default {
	name: 'CollectivePrint',

	components: {
		EmptyContent,
		PagePrint,
	},

	data() {
		return {
			loading: true,
			waitingFor: [],
		}
	},

	computed: {
		...mapGetters([
			'pagesTreeWalk',
			'shareTokenParam',
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
				this.loading = false
				this.$nextTick(() => {
					// Wait a few milliseconds to load images
					setTimeout(() => {
						document.getElementById('content-vue').scrollIntoView()
						// Scroll back to the beginning of the document
						window.print()
					}, 600)
				})
			}
		},
	},
}
</script>
