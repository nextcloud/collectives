<template>
	<div class="recent-pages-widget">
		<a class="recent-pages-title"
			@keydown.enter="toggleWidget"
			@click="toggleWidget">
			<WidgetHeading :title="t('collectives', 'Recent pages')" />
			<div class="toggle-icon">
				<ChevronDownButton :size="24"
					:title="t('collectives', 'Expand recent pages')"
					:class="{ 'collapsed': !showWidget }" />
			</div>
		</a>
		<div v-if="showWidget" class="recent-pages-widget-container">
			<div ref="pageslider" class="recent-pages-widget-pages">
				<RecentPageTile v-for="page in trimmedRecentPages"
					:key="page.id"
					:page="page" />
			</div>
			<div class="recent-pages-widget-buttons">
				<button ref="buttonslideleft"
					class="button-slide button-slide__left hidden"
					:aria-label="t('collectives', 'Scroll recent pages to the left')"
					@click="slideLeft"
					@keypress.enter.prevent="slideLeft">
					<ChevronLeftButton :size="44" />
				</button>
				<button ref="buttonslideright"
					class="button-slide button-slide__right hidden"
					:aria-label="t('collectives', 'Scroll recent pages to the left')"
					@click="slideRight"
					@keypress.enter.prevent="slideRight">
					<ChevronRightButton :size="44" />
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'
import { mapGetters } from 'vuex'
import ChevronDownButton from 'vue-material-design-icons/ChevronDown.vue'
import ChevronLeftButton from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightButton from 'vue-material-design-icons/ChevronRight.vue'
import RecentPageTile from './RecentPageTile.vue'
import WidgetHeading from './WidgetHeading.vue'

const SLIDE_OFFSET = 198

export default {
	name: 'RecentPagesWidget',

	components: {
		ChevronDownButton,
		ChevronLeftButton,
		ChevronRightButton,
		RecentPageTile,
		WidgetHeading,
	},

	data() {
		return {
			showWidget: true,
		}
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

	mounted() {
		this.$nextTick(() => {
			this.updateButtons()
		})
		this.$refs.pageslider.addEventListener('scroll', this.updateButtons)
	},

	unmounted() {
		this.$refs.pageslider.removeEventListener('scroll', this.updateButtons)
	},

	methods: {
		toggleWidget() {
			this.showWidget = !this.showWidget
			if (this.showWidget) {
				this.updateButtons()
			}
		},

		updateButtons: debounce(function() {
			const pagesliderEl = this.$refs.pageslider
			if (!pagesliderEl) {
				return
			}
			if (pagesliderEl.scrollLeft <= 0) {
				this.$refs.buttonslideleft.classList.add('hidden')
			} else {
				this.$refs.buttonslideleft.classList.remove('hidden')
			}

			if (pagesliderEl.scrollLeft >= pagesliderEl.scrollLeftMax) {
				this.$refs.buttonslideright.classList.add('hidden')
			} else {
				this.$refs.buttonslideright.classList.remove('hidden')
			}
		}, 50),

		slideLeft() {
			const pagesliderEl = this.$refs.pageslider
			const newScrollLeft = Math.max(0, pagesliderEl.scrollLeft -= SLIDE_OFFSET)
			pagesliderEl.scrollTo({
				top: pagesliderEl.scrollTop,
				left: pagesliderEl.scrollLeft = newScrollLeft,
				behavior: 'smooth',
			})
			this.updateButtons()
		},

		slideRight() {
			const pagesliderEl = this.$refs.pageslider
			const scrollLeftMax = pagesliderEl.scrollWidth - pagesliderEl.clientWidth
			const newScrollLeft = Math.min(scrollLeftMax, pagesliderEl.scrollLeft += SLIDE_OFFSET)
			pagesliderEl.scrollTo({
				top: pagesliderEl.scrollTop,
				left: pagesliderEl.scrollLeft = newScrollLeft,
				behavior: 'smooth',
			})
			this.updateButtons()
		},
	},
}
</script>

<style lang="scss" scoped>
.recent-pages-title {
	display: flex;

	.toggle-icon {
		padding-top: 25px;
		padding-left: 8px;

		.collapsed {
			transition: transform var(--animation-slow);
			transform: rotate(-90deg);
		}
	}
}

.recent-pages-widget-container {
	position: relative;
	padding-top: 12px;

	.recent-pages-widget-pages {
		display: flex;
		flex-direction: row;
		gap: 12px;

		overflow-x: auto;
		scroll-snap-type: x mandatory;
		// Hide scrollbar
		scrollbar-width: none;
		-ms-overflow-style: none;
		&::-webkit-scrollbar {
			display: none;
		}
	}
}

.button-slide {
	position: absolute;
	width: 100px;
	height: 100%;
	display: flex;
	right: 0;
	top: 0;
	bottom: 0;
	padding: 0;
	margin: 0 !important;
	border: 0;
	border-radius: 0;
	z-index: 2;

	&__left {
		left: 0;
		justify-content: left;
		background: linear-gradient(to left, rgba(0, 0, 0, 0), var(--color-main-background));

		&:active {
			background: linear-gradient(to left, rgba(0, 0, 0, 0), var(--color-main-background)) !important;
		}
	}

	&__right {
		right: 0;
		justify-content: right;
		background: linear-gradient(to right, rgba(0, 0, 0, 0), var(--color-main-background));

		&:active {
			background: linear-gradient(to right, rgba(0, 0, 0, 0), var(--color-main-background)) !important;
		}
	}

	&.hidden {
		display: none;
	}
}
</style>
