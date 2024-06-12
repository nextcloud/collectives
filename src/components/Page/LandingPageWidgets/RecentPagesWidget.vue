<template>
	<div class="recent-pages-widget">
		<a class="recent-pages-title"
			:aria-label="expandLabel"
			@keydown.enter="toggleWidget"
			@click="toggleWidget">
			<WidgetHeading :title="t('collectives', 'Recent pages')" />
			<div class="toggle-icon">
				<ChevronDownIcon :size="24"
					:class="{ 'collapsed': !showRecentPages }" />
			</div>
		</a>
		<div v-show="showRecentPages" class="recent-pages-widget-container">
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
					<ChevronLeftIcon :size="44" />
				</button>
				<button ref="buttonslideright"
					class="button-slide button-slide__right hidden"
					:aria-label="t('collectives', 'Scroll recent pages to the left')"
					@click="slideRight"
					@keypress.enter.prevent="slideRight">
					<ChevronRightIcon :size="44" />
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import debounce from 'debounce'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import { showError } from '@nextcloud/dialogs'
import ChevronDownIcon from 'vue-material-design-icons/ChevronDown.vue'
import ChevronLeftIcon from 'vue-material-design-icons/ChevronLeft.vue'
import ChevronRightIcon from 'vue-material-design-icons/ChevronRight.vue'
import RecentPageTile from './RecentPageTile.vue'
import WidgetHeading from './WidgetHeading.vue'
import { PATCH_COLLECTIVE_WITH_PROPERTY } from '../../../store/mutations.js'
import { SET_COLLECTIVE_USER_SETTING_SHOW_RECENT_PAGES } from '../../../store/actions.js'

const SLIDE_OFFSET = 198

export default {
	name: 'RecentPagesWidget',

	components: {
		ChevronDownIcon,
		ChevronLeftIcon,
		ChevronRightIcon,
		RecentPageTile,
		WidgetHeading,
	},

	data() {
		return {
			updateButtonsDebounced: debounce(this.updateButtons, 50),
		}
	},

	computed: {
		...mapGetters([
			'currentCollective',
			'isPublic',
			'recentPages',
		]),

		expandLabel() {
			return this.showRecentPages
				? t('collectives', 'Collapse recent pages')
				: t('collectives', 'Expand recent pages')
		},

		showRecentPages() {
			return this.currentCollective.userShowRecentPages ?? true
		},

		trimmedRecentPages() {
			return this.recentPages
				.slice(0, 10)
		},
	},

	mounted() {
		this.$nextTick(() => {
			this.updateButtonsDebounced()
		})
		this.$refs.pageslider.addEventListener('scroll', this.updateButtonsDebounced)
	},

	unmounted() {
		this.$refs.pageslider.removeEventListener('scroll', this.updateButtonsDebounced)
	},

	methods: {
		...mapMutations({
			patchCollectiveWithProperty: PATCH_COLLECTIVE_WITH_PROPERTY,
		}),

		...mapActions({
			dispatchSetUserShowRecentPages: SET_COLLECTIVE_USER_SETTING_SHOW_RECENT_PAGES,
		}),

		toggleWidget() {
			if (this.isPublic) {
				this.patchCollectiveWithProperty({ id: this.currentCollective.id, property: 'userShowRecentPages', value: !this.showRecentPages })
			} else {
				this.dispatchSetUserShowRecentPages({ id: this.currentCollective.id, showRecentPages: !this.showRecentPages })
					.catch((error) => {
						console.error(error)
						showError(t('collectives', 'Could not save recent pages setting for collective'))
					})
			}

			if (this.showRecentPages) {
				this.updateButtonsDebounced()
			}
		},

		updateButtons() {
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
		},

		slideLeft() {
			const pagesliderEl = this.$refs.pageslider
			const newScrollLeft = Math.max(0, pagesliderEl.scrollLeft -= SLIDE_OFFSET)
			pagesliderEl.scrollTo({
				top: pagesliderEl.scrollTop,
				left: pagesliderEl.scrollLeft = newScrollLeft,
				behavior: 'smooth',
			})
			this.updateButtonsDebounced()
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
			this.updateButtonsDebounced()
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
