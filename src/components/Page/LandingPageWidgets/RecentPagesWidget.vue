<template>
	<div class="recent-pages-widget">
		<WidgetHeading :title="t('collectives', 'Recent pages')" />
		<div ref="pageslider" class="recent-pages-widget-pages">
			<button ref="buttonslideleft"
				class="button-slide button-slide__left hidden"
				:aria-label="t('collectives', 'Scroll recent pages to the left')"
				@click="slideLeft"
				@keypress.enter.prevent="slideLeft">
				<ChevronLeftButton :size="44" />
			</button>
			<RecentPageTile v-for="page in trimmedRecentPages"
				:key="page.id"
				:page="page" />
			<button ref="buttonslideright"
				class="button-slide button-slide__right"
				:aria-label="t('collectives', 'Scroll recent pages to the left')"
				@click="slideRight"
				@keypress.enter.prevent="slideRight">
				<ChevronRightButton :size="44" />
			</button>
		</div>
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import ChevronLeftButton from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightButton from 'vue-material-design-icons/ChevronRight.vue'
import RecentPageTile from './RecentPageTile.vue'
import WidgetHeading from './WidgetHeading.vue'

const SLIDE_OFFSET = 198

export default {
	name: 'RecentPagesWidget',

	components: {
		ChevronLeftButton,
		ChevronRightButton,
		RecentPageTile,
		WidgetHeading,
	},

	computed: {
		...mapGetters([
			'recentPages',
		]),

		trimmedRecentPages() {
			return this.recentPages
				.slice(0, 10)
		},
	},

	methods: {
		updateButtons() {
			const pagesliderEl = this.$refs.pageslider
			if (pagesliderEl.scrollLeft <= 0) {
				this.$refs.buttonslideleft.classList.add('hidden')
			} else {
				this.$refs.buttonslideleft.classList.remove('hidden')
			}

			console.debug('pageslider', this.$refs.pageslider)
			if (pagesliderEl.scrollLeft >= pagesliderEl.scrollLeftMax) {
				this.$refs.buttonslideright.classList.add('hidden')
			} else {
				this.$refs.buttonslideright.classList.remove('hidden')
			}
		},

		slideLeft() {
			this.$refs.pageslider.scrollLeft -= (SLIDE_OFFSET)
			this.updateButtons()
		},

		slideRight() {
			this.$refs.pageslider.scrollLeft += (SLIDE_OFFSET)
			this.updateButtons()
		},
	},
}
</script>

<style lang="scss" scoped>
.recent-pages-widget-pages {
	position: relative;
	display: flex;
	flex-direction: row;
	gap: 12px;
	padding-top: 12px;

	max-width: 670px;
	overflow-x: auto;
	scrollbar-width: none;
}

.button-slide {
	position: sticky;
	display: flex;
	top: 0;
	padding: 0;
	border: 0;
	border-radius: 0;
	z-index: 2;

	&__left {
		left: 0;
		padding-right: 48px;
		background: linear-gradient(to left, rgba(0, 0, 0, 0), var(--color-main-background));
	}

	&__right {
		right: 0;
		padding-left: 48px;
		background: linear-gradient(to right, rgba(0, 0, 0, 0), var(--color-main-background));
	}

	&.hidden {
		display: none;
	}
}
</style>
