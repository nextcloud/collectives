<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContentList :show-details="showing('details')">
		<div class="page-list-headerbar">
			<NcTextField name="pageFilter"
				:label="t('collectives', 'Search pages')"
				:value.sync="filterString"
				class="page-filter"
				:placeholder="t('collectives', 'Search pages ...')"
				trailing-button-icon="close"
				:show-trailing-button="isFilteredView"
				@trailing-button-click="clearFilterString" />
			<NcActions class="toggle"
				:aria-label="t('collectives', 'Sort order')">
				<template #icon>
					<SortAscendingIcon v-if="sortedBy('byOrder')" :size="16" />
					<SortAlphabeticalAscendingIcon v-else-if="sortedBy('byTitle')" :size="16" />
					<SortClockAscendingIcon v-else :size="16" />
				</template>
				<NcActionButton class="toggle-button"
					:class="{selected: sortedBy('byOrder')}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byOrder')">
					<template #icon>
						<SortAscendingIcon :size="20" />
					</template>
					{{ t('collectives', 'Sort by custom order') }}
				</NcActionButton>
				<NcActionButton class="toggle-button"
					:class="{selected: sortedBy('byTimestamp')}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byTimestamp')">
					<template #icon>
						<SortClockAscendingIcon :size="20" />
					</template>
					{{ t('collectives', 'Sort recently changed first') }}
				</NcActionButton>
				<NcActionButton class="toggle-button"
					:class="{selected: sortedBy('byTitle')}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byTitle')">
					<template #icon>
						<SortAlphabeticalAscendingIcon :size="20" />
					</template>
					{{ t('collectives', 'Sort by title') }}
				</NcActionButton>
			</NcActions>
		</div>
		<div v-if="!currentCollective || !rootPage || loading('pagelist')" class="page-list">
			<SkeletonLoading type="items" :count="3" />
		</div>
		<div v-else class="page-list">
			<!-- Landing page -->
			<Item key="Readme"
				:to="currentCollectivePath"
				:page-id="rootPage.id"
				:parent-id="0"
				:title="currentCollectiveIsPageShare ? rootPage.title : currentCollective.name"
				:timestamp="rootPage.timestamp"
				:last-user-id="rootPage.lastUserId"
				:last-user-display-name="rootPage.lastUserDisplayName"
				:emoji="currentCollectiveIsPageShare ? rootPage.emoji : currentCollective.emoji"
				:level="0"
				:can-edit="currentCollectiveCanEdit"
				:is-root-page="true"
				:is-landing-page="!currentCollectiveIsPageShare"
				:filtered-view="false"
				class="page-list-root-page"
				@click.native="show('details')" />

			<!-- Sort order container (optional) -->
			<div v-if="!sortedBy('byOrder')" class="sort-order-container">
				<span class="sort-order-chip">
					{{ sortedBy('byTitle') ? t('collectives', 'Sorted by title') : t('collectives', 'Sorted by recently changed') }}
					<NcButton :aria-label="t('collectives', 'Switch back to default sort order')"
						type="tertiary"
						class="sort-oder-chip-button"
						@click="sortPagesAndScroll('byOrder')">
						<template #icon>
							<CloseIcon :size="20" />
						</template>
					</NcButton>
				</span>
			</div>

			<!-- Favorites -->
			<PageFavorites v-if="showFavorites" />

			<!-- Filtered view page list -->
			<div v-if="isFilteredView" ref="pageListFiltered" class="page-list-filtered">
				<NcAppNavigationCaption v-if="filteredPages.length > 0" :name="t('Collectives','Results in title')" />
				<RecycleScroller v-if="filteredPages.length > 0"
					v-slot="{ item }"
					ref="filteredScroller"
					:items="filteredPages"
					:item-size="itemSize"
					key-field="id">
					<SubpageList :key="item.id"
						:data-page-id="item.id"
						:page="item"
						:level="1"
						:filtered-view="true"
						class="page-list-drag-item" />
				</RecycleScroller>
				<NcAppNavigationCaption v-if="loadingContentFilteredPages || contentFilteredPages.length > 0" :name="t('Collectives', 'Results in content')" />
				<RecycleScroller v-if="!loadingContentFilteredPages && contentFilteredPages.length > 0"
					v-slot="{ item }"
					ref="contentFilteredScroller"
					:items="contentFilteredPages"
					:item-size="itemSize"
					key-field="id">
					<SubpageList :key="item.id"
						:data-page-id="item.id"
						:page="item"
						:level="1"
						:filtered-view="true"
						class="page-list-drag-item" />
				</RecycleScroller>
				<div v-if="loadingContentFilteredPages" class="scrollload">
					<SkeletonLoading type="items" :count="3" />
				</div>
			</div>

			<!-- Unfiltered view page list -->
			<Draggable v-else
				:list="subpages"
				:parent-id="rootPage.id"
				:disable-sorting="isFilteredView">
				<SubpageList v-for="page in subpages"
					:key="page.id"
					:data-page-id="page.id"
					:page="page"
					:level="1"
					:filtered-view="false"
					class="page-list-drag-item" />
			</Draggable>
		</div>

		<!-- Page trash -->
		<PageTrash v-if="displayTrash" />

		<NewPageDialog v-if="newPageParentId" />
	</NcAppContentList>
</template>

<script>

import { mapActions, mapState } from 'pinia'
import { ref } from 'vue'
import { useElementSize } from '@vueuse/core'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useSearchStore } from '../stores/search.js'
import { NcAppNavigationCaption, NcActionButton, NcActions, NcAppContentList, NcButton, NcTextField } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import Draggable from './PageList/Draggable.vue'
import NewPageDialog from './PageList/NewPageDialog.vue'
import SubpageList from './PageList/SubpageList.vue'
import Item from './PageList/Item.vue'
import PageFavorites from './PageList/PageFavorites.vue'
import PageTrash from './PageList/PageTrash.vue'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortClockAscendingIcon from 'vue-material-design-icons/SortClockAscending.vue'
import { scrollToPage } from '../util/scrollToElement.js'
import { pageOrders } from '../util/sortOrders.js'
import SkeletonLoading from './SkeletonLoading.vue'
import { RecycleScroller } from 'vue-virtual-scroller'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import debounce from 'debounce'
import { contentSearchPages } from '../apis/collectives/pages.js'

export default {
	name: 'PageList',

	components: {
		SkeletonLoading,
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcButton,
		NcTextField,
		NewPageDialog,
		CloseIcon,
		Draggable,
		Item,
		PageFavorites,
		PageTrash,
		SubpageList,
		SortAlphabeticalAscendingIcon,
		SortAscendingIcon,
		SortClockAscendingIcon,
		RecycleScroller,
		NcAppNavigationCaption,
	},

	setup() {
		const pageListFiltered = ref()
		const { height: pageListFilteredHeight } = useElementSize(pageListFiltered)
		return { pageListFiltered, pageListFilteredHeight }
	},

	data() {
		return {
			filterString: '',
			contentFilteredPages: [],
			loadingContentFilteredPages: false,
			getContentFilteredPagesDebounced: debounce(this.getContentFilteredPages, 700),
		}
	},

	computed: {
		...mapState(useRootStore, ['isPublic', 'loading', 'showing']),
		...mapState(useCollectivesStore, [
			'currentCollective',
			'currentCollectiveCanEdit',
			'currentCollectiveIsPageShare',
			'currentCollectivePath',
		]),
		...mapState(usePagesStore, [
			'rootPage',
			'currentPage',
			'newPageParentId',
			'hasFavoritePages',
			'keptSortable',
			'visibleSubpages',
			'sortByOrder',
			'allPagesSorted',
		]),

		allPagesSortedCached() {
			return this.rootPage
				? this.allPagesSorted(this.rootPage.id)
				: []
		},

		filteredPages() {
			return this.allPagesSortedCached.filter(p => {
				return p.title.toLowerCase().includes(this.filterString.toLowerCase())
			})
		},

		subpages() {
			if (this.rootPage) {
				return this.visibleSubpages(this.rootPage.id)
			} else {
				return []
			}
		},

		sortedBy() {
			return (sortOrder) => this.sortByOrder === sortOrder
		},

		showFavorites() {
			return !this.isFilteredView && this.hasFavoritePages
		},

		isFilteredView() {
			return this.filterString !== ''
		},

		defaultClickableArea() {
			return parseInt(window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area'))
		},

		scrollerMaxHeights() {
			const navigationCaptionHeight = this.defaultClickableArea + 4
			const navigationCaptionMargin = this.defaultClickableArea / 2
			const fullScrollerHeight = Math.floor(this.pageListFilteredHeight - navigationCaptionHeight)
			const halfScrollerHeight = Math.floor((this.pageListFilteredHeight - navigationCaptionHeight * 2) / 2) - navigationCaptionMargin
			// Split half/half during loading
			if (this.loadingContentFilteredPages) {
				return [halfScrollerHeight, halfScrollerHeight]
			}

			// If only one filter has items, give all height to it
			if (!this.filteredPages.length) {
				return [0, fullScrollerHeight]
			}

			// If only one filter has items, give all height to it
			if (!this.contentFilteredPages.length) {
				return [fullScrollerHeight, 0]
			}

			const filteredPagesFullHeight = this.filteredPages.length * this.itemSize
			const contentFilteredPagesFullHeight = this.contentFilteredPages.length * this.itemSize
			// If both filters grow above available space, split half/half
			if (filteredPagesFullHeight > halfScrollerHeight && contentFilteredPagesFullHeight > halfScrollerHeight) {
				return [halfScrollerHeight, halfScrollerHeight]
			}

			// If one filter doesn't need half of the space, give the rest to the other
			return filteredPagesFullHeight < halfScrollerHeight
				? [filteredPagesFullHeight, halfScrollerHeight * 2 - filteredPagesFullHeight]
				: [halfScrollerHeight * 2 - contentFilteredPagesFullHeight, contentFilteredPagesFullHeight]
		},

		itemSize() {
			return this.defaultClickableArea > 40
				? this.defaultClickableArea
				: this.defaultClickableArea + 4
		},

		displayTrash() {
			return this.currentCollectiveCanEdit
				&& !this.currentCollectiveIsPageShare
				&& ('files_trashbin' in this.OC.appswebroots)
				&& !this.loading('collectives')
		},
	},

	watch: {
		'currentCollective.id'() {
			this.contentFilteredPages = []
			this.getContentFilteredPagesDebounced()
		},
		filterString() {
			this.getContentFilteredPagesDebounced()
			this.setSearchQuery(this.filterString)
		},
		contentFilteredPages() {
			this.updateScrollerHeights()
		},
		pageListFilteredHeight() {
			this.updateScrollerHeights()
		},
	},

	methods: {
		...mapActions(useRootStore, ['show']),
		...mapActions(useCollectivesStore, ['setCollectiveUserSettingPageOrder']),
		...mapActions(usePagesStore, ['setPageOrder']),
		...mapActions(useSearchStore, ['setSearchQuery']),

		clearFilterString() {
			this.filterString = ''
		},

		/**
		 * Change page sort order and scroll to current page
		 *
		 * @param { string } order Sort order
		 */
		sortPagesAndScroll(order) {
			this.setPageOrder(order)
			if (!this.isPublic) {
				this.setCollectiveUserSettingPageOrder({ id: this.currentCollective.id, pageOrder: pageOrders[order] })
					.catch((error) => {
						console.error(error)
						showError(t('collectives', 'Could not save page order for collective'))
					})
			}
			this.$nextTick(() => {
				scrollToPage(this.currentPage.id)
			})
		},

		updateScrollerHeights() {
			const [filteredPagesScrollerMaxHeight, contentFilteredPagesScrollerMaxHeight] = this.scrollerMaxHeights
			this.$nextTick(() => {
				if (this.$refs.filteredScroller) {
					this.$refs.filteredScroller.$el.style.maxHeight = filteredPagesScrollerMaxHeight + 'px'
				}
				if (this.$refs.contentFilteredScroller) {
					this.$refs.contentFilteredScroller.$el.style.maxHeight = contentFilteredPagesScrollerMaxHeight + 'px'
				}
			})
		},

		async getContentFilteredPages() {
			if (!this.filterString) {
				this.contentFilteredPages = []
				return
			}

			this.loadingContentFilteredPages = true
			const oldFilterString = this.filterString
			this.contentFilteredPages = (await contentSearchPages(this.currentCollective.id, this.filterString)).data.data

			// prevent showing old results
			if (oldFilterString === this.filterString) {
				this.loadingContentFilteredPages = false
			}
		},
	},
}

</script>

<style lang="scss">
:root {
	--page-list-header-height: calc(var(--default-clickable-area) + 14px);
	--landing-page-height: calc(var(--default-clickable-area) + 8px);
	--page-trash-height: calc(var(--default-clickable-area) + 24px);
	--page-list-height: calc(100vh - var(--header-height) - var(--page-list-header-height) - var(--landing-page-height) - var(--page-trash-height));
	--navigation-caption-height: calc(var(--default-clickable-area) + 4px + (var(--default-clickable-area) / 2));
}
</style>

<style lang="scss" scoped>
.scroller {
	// NC header bar 50px; page list header bar; landing page; page trash; NcAppNavigationCaption 78px divided by 2 for multiple scrollers
	max-height: calc((var(--page-list-height) - var(--navigation-caption-height) * 2) / 2);
}

.fullscroller{
	// NC header bar 50px; page list header bar; landing page; page trash; NcAppNavigationCaption 78px
	max-height: calc(var(--page-list-height) - var(--navigation-caption-height));
}

.app-content-list {
	// nextcloud-vue component sets `max-height: unset` on mobile.
	// Overwrite this to fix stickiness of header and rootpage.
	max-height: 100%;
}

.page-list-headerbar {
	display: flex;
	flex-direction: row;
	gap: 2px;
	min-height: var(--page-list-header-height);
	background-color: var(--color-main-background);
	align-items: center;
	justify-content: space-between;
	margin-right: 4px;

	.page-filter {
		margin-left: calc(var(--default-clickable-area) + 12px) !important;
		padding-bottom: 6px;
	}
}

.toggle {
	height: var(--default-clickable-area);
	width: var(--default-clickable-area);
	padding: 0;
}

.toggle:hover {
	opacity: 1;
}

li.toggle-button.selected {
	background-color: var(--color-primary-element-light);
}

.page-list {
	overflow-y: auto;
	display: flex;
	flex-direction: column;
	flex-grow: 1;
	padding: 0 4px;
}

.page-list-root-page {
	position: sticky;
	top: 0;
	z-index: 1;
	background-color: var(--color-main-background);
	margin-block-end: 8px;
}

.page-list-filtered {
	flex-grow: 1;
	max-height: 100%;
	overflow: hidden;
}

.sort-order-container {
	display: flex;
	align-items: center;

	position: sticky;
	// landing page + 8px margin-bottom
	top: var(--landing-page-height);
	z-index: 1;
	background-color: var(--color-main-background);
	border-bottom: 4px solid var(--color-main-background);

	.sort-order-chip {
		display: flex;
		flex-direction: row;
		align-items: center;

		height: 24px;
		padding: 7px;
		margin-left: 33px; // 40px - 7px
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-element, var(--border-radius-large));

		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;

		.sort-oder-chip-button {
			min-height: 20px;
			min-width: 20px;
			height: 20px;
			width: 20px !important;
			padding: 7px;
			margin-left: 10px;
		}
	}
}
</style>
