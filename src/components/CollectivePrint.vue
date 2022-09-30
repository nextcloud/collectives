<template>
	<div>
		<input id="sharingToken"
			type="hidden"
			name="sharingToken"
			:value="shareTokenParam">
		<NcEmptyContent v-show="loading"
			:title="t('collectives', 'Preparing collective for exporting or printing')">
			<template #icon>
				<DownloadIcon />
			</template>
			<template #action>
				<NcProgressBar :value="loadingProgress" size="medium">
					{{ loadingProgress }}
				</NcProgressBar>
				<ul class="load-messages">
					<li v-for="task in [loadPages, loadImages]"
						v-show="task.total"
						:key="task.message">
						{{ task.message }}
						{{ task.total ? `${task.count} / ${task.total}` : '' }}
					</li>
				</ul>
			</template>
		</NcEmptyContent>
		<div v-for="page in pagesTreeWalk()" v-show="!loading" :key="page.id">
			<PagePrint :page="page"
				@loading="waitingFor.push(page.id)"
				@ready="ready(page.id)" />
		</div>
	</div>
</template>

<script>
import { mapGetters, mapActions } from 'vuex'
import { NcEmptyContent } from '@nextcloud/vue'
import NcProgressBar from '@nextcloud/vue/dist/Components/NcProgressBar.js'
import DownloadIcon from 'vue-material-design-icons/Download.vue'
import PagePrint from './PagePrint.vue'
import { GET_PAGES } from '../store/actions.js'
import displayError from '../util/displayError.js'

export default {
	name: 'CollectivePrint',

	components: {
		NcEmptyContent,
		PagePrint,
		DownloadIcon,
		NcProgressBar,
	},

	data() {
		return {
			loading: true,
			waitingFor: [],
			loadPages: {
				message: t('collectives', 'Loading pages:'),
				count: 0,
				total: 0,
			},
			loadImages: {
				message: t('collectives', 'Loading images:'),
				count: 0,
				total: 0,
			},
		}
	},

	computed: {
		...mapGetters([
			'pagesTreeWalk',
			'shareTokenParam',
		]),

		loadingCount() {
			return this.loadPages.count + this.loadImages.count
		},

		loadingTotal() {
			return this.loadPages.total + this.loadImages.total
		},

		loadingProgress() {
			return this.loadingTotal
				? this.loadingCount / this.loadingTotal * 100
				: 0
		},
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
			this.loadPages.total = this.pagesTreeWalk().length
		},

		ready(pageId) {
			if (this.waitingFor.indexOf(pageId) >= 0) {
				this.waitingFor.splice(this.waitingFor.indexOf(pageId), 1)
				this.loadPages.count += 1
			}
			if (!this.waitingFor.length) {
				this.$nextTick(this.waitForImages)
			}
		},

		waitForImages() {
			const images = document.querySelectorAll('#text-container div.image')
			const loading = document.querySelectorAll('#text-container div.image.icon-loading')
			this.loadImages.total = images.length
			this.loadImages.count = images.length - loading.length

			if (!loading.length) {
				this.allImagesLoaded()
			}

			for (const el of loading) {
				// Hook into the capture phase as `load` events do not bubble up.
				el.addEventListener('load', this.imageLoaded, { capture: true })
			}
		},

		imageLoaded(event) {
			if (!event.target.classList.contains('image__main')) {
				return
			}
			this.loadImages.count += 1
			if (this.loadImages.count >= this.loadImages.total) {
				// Finish loading the image
				this.$nextTick(() => {
					setTimeout(this.allImagesLoaded, 100)
				})
			}
		},

		allImagesLoaded() {
			this.loading = false
			this.$nextTick(() => {
				// Scroll back to the beginning of the document
				document.getElementById('content-vue').scrollIntoView()
				window.print()
			})
		},
	},
}
</script>

<style scoped>
.progress-bar {
	margin-top: 8px;
}

.load-messages {
	color: var(--color-text-maxcontrast);
}
</style>
