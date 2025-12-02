<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContentList :show-details="showing('details')">
		<!-- Headerbar with filter field and sort selector -->
		<div class="page-list-headerbar">
			<!-- Tag selection popover -->
			<NcPopover
				popup-role="listbox"
				class="page-filter"
				popover-base-class="page-filter-popover"
				:shown="showTagSelection"
				:triggers="[]"
				placement="bottom-start"
				no-focus-trap>
				<template #trigger="{ attrs }">
					<NcTextField
						ref="pageFilter"
						v-model="filterString"
						name="pageFilter"
						v-bind="attrs"
						:label="t('collectives', 'Search pages')"
						:placeholder="t('collectives', 'Search pages…')"
						trailing-button-icon="close"
						:show-trailing-button="isFilteredView"
						@trailing-button-click="clearFilterString"
						@keydown.esc.prevent.stop="stopTagSelection"
						@keydown.tab="onPageFilterTabKey" />
				</template>
				<template #default>
					<div
						class="page-filter-tag-select"
						@keydown.esc.prevent.stop="stopTagSelection">
						<ul class="page-tags select-popover">
							<PageTag
								v-for="tag in filterStringTags"
								ref="filterStringTag"
								:key="tag.id"
								:tag="tag"
								@select="onSelectFilterTag(tag.id)" />
						</ul>
					</div>
				</template>
			</NcPopover>

			<NcActions
				class="toggle"
				:aria-label="t('collectives', 'Sort order')">
				<template #icon>
					<SortAscendingIcon v-if="sortedBy('byOrder')" :size="16" />
					<SortAlphabeticalAscendingIcon v-else-if="sortedBy('byTitleAsc')" :size="16" />
					<SortAlphabeticalDescendingIcon v-else-if="sortedBy('byTitleDesc')" :size="16" />
					<SortClockAscendingIcon v-else-if="sortedBy('byTimeAsc')" :size="16" />
					<SortClockDescendingIcon v-else :size="16" />
				</template>
				<NcActionButton
					class="toggle-button"
					:class="{ selected: sortedBy('byOrder') }"
					:close-after-click="true"
					@click="sortPagesAndScroll('byOrder')">
					<template #icon>
						<SortAscendingIcon :size="20" />
					</template>
					{{ t('collectives', 'Sort by custom order') }}
				</NcActionButton>
				<NcActionButton
					class="toggle-button"
					:class="{ selected: sortedBy('byTimeAsc') || sortedBy('byTimeDesc') }"
					:close-after-click="true"
					@click="sortedBy('byTimeAsc') ? sortPagesAndScroll('byTimeDesc') : sortPagesAndScroll('byTimeAsc')">
					<template #icon>
						<SortClockAscendingIcon v-if="!sortedBy('byTimeAsc')" :size="20" />
						<SortClockDescendingIcon v-else :size="20" />
					</template>
					{{ sortedBy('byTimeAsc') ? t('collectives', 'Sort least recently changed first') : t('collectives', 'Sort recently changed first') }}
				</NcActionButton>
				<NcActionButton
					class="toggle-button"
					:class="{ selected: sortedBy('byTitleAsc') || sortedBy('byTitleDesc') }"
					:close-after-click="true"
					@click="sortedBy('byTitleAsc') ? sortPagesAndScroll('byTitleDesc') : sortPagesAndScroll('byTitleAsc')">
					<template #icon>
						<SortAlphabeticalAscendingIcon v-if="!sortedBy('byTitleAsc')" :size="20" />
						<SortAlphabeticalDescendingIcon v-else :size="20" />
					</template>
					{{ sortedBy('byTitleAsc') ? t('collectives', 'Sort descending by title') : t('collectives', 'Sort ascending by title') }}
				</NcActionButton>
			</NcActions>
		</div>

		<!-- Filter tags -->
		<div class="page-filter-tags">
			<ul class="page-tags">
				<PageTag
					v-for="tag in filterTags"
					:key="tag.id"
					:tag="tag"
					:can-remove="true"
					@remove="removeFilterTagId(tag.id)" />
			</ul>
		</div>

		<!-- Loading -->
		<div v-if="!currentCollective || !rootPage || loading('pagelist')" class="page-list">
			<SkeletonLoading type="items" :count="3" />
		</div>

		<!-- Page list -->
		<div v-else class="page-list">
			<!-- Landing page -->
			<PageListItem
				key="Readme"
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
				:network-online="networkOnline"
				class="page-list-root-page"
				@click.native="show('details')" />

			<!-- Sort order container (optional) -->
			<div v-if="!sortedBy('byOrder')" class="sort-order-container">
				<div class="sort-order-chip">
					<span class="sort-order-chip-text">
						{{ sortedByString }}
					</span>
					<NcButton
						:aria-label="t('collectives', 'Switch back to default sort order')"
						variant="tertiary"
						class="sort-order-chip-button"
						@click="sortPagesAndScroll('byOrder')">
						<template #icon>
							<CloseIcon :size="20" />
						</template>
					</NcButton>
				</div>
			</div>

			<!-- Favorites -->
			<PageFavorites v-if="showFavorites" :network-online="networkOnline" />

			<!-- Filtered view page list -->
			<div v-if="isFilteredView" ref="pageListFiltered" class="page-list-filtered">
				<NcAppNavigationCaption v-if="filteredPages.length > 0" :name="t('Collectives', 'Results in title or tags')" />
				<RecycleScroller
					v-if="filteredPages.length > 0"
					v-slot="{ item }"
					ref="filteredScroller"
					:items="filteredPages"
					:item-size="itemSize"
					key-field="id">
					<SubpageList
						:key="item.id"
						:data-page-id="item.id"
						:page="item"
						:level="1"
						:filtered-view="true"
						:network-online="networkOnline"
						class="page-list-drag-item" />
				</RecycleScroller>
				<NcAppNavigationCaption v-if="loadingContentFilteredPages || contentFilteredPages.length > 0" :name="t('Collectives', 'Results in content')" />
				<RecycleScroller
					v-if="!loadingContentFilteredPages && contentFilteredPages.length > 0"
					v-slot="{ item }"
					ref="contentFilteredScroller"
					:items="contentFilteredPages"
					:item-size="itemSize"
					key-field="id">
					<SubpageList
						:key="item.id"
						:data-page-id="item.id"
						:page="item"
						:level="1"
						:filtered-view="true"
						:network-online="networkOnline"
						class="page-list-drag-item" />
				</RecycleScroller>
				<div v-if="loadingContentFilteredPages" class="scrollload">
					<SkeletonLoading type="items" :count="3" />
				</div>
			</div>

			<!-- Unfiltered view page list -->
			<DraggableElement
				v-else
				class="page-list-dragarea"
				:list="subpages"
				:parent-id="rootPage.id"
				:disable-sorting="isFilteredView">
				<SubpageList
					v-for="page in subpages"
					:key="page.id"
					:data-page-id="page.id"
					:page="page"
					:level="1"
					:filtered-view="false"
					:network-online="networkOnline"
					class="page-list-drag-item" />
			</DraggableElement>
		</div>

		<!-- Page trash -->
		<PageTrash v-if="displayTrash" :network-online="networkOnline" />

		<NewPageDialog v-if="newPageParentId" />
	</NcAppContentList>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { NcActionButton, NcActions, NcAppContentList, NcAppNavigationCaption, NcButton, NcPopover, NcTextField } from '@nextcloud/vue'
import { useElementSize } from '@vueuse/core'
import debounce from 'debounce'
import { mapActions, mapState } from 'pinia'
import { ref } from 'vue'
import { RecycleScroller } from 'vue-virtual-scroller'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue'
import SortAlphabeticalDescendingIcon from 'vue-material-design-icons/SortAlphabeticalDescending.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortClockAscendingIcon from 'vue-material-design-icons/SortClockAscendingOutline.vue'
import SortClockDescendingIcon from 'vue-material-design-icons/SortClockDescendingOutline.vue'
import DraggableElement from './PageList/DraggableElement.vue'
import NewPageDialog from './PageList/NewPageDialog.vue'
import PageFavorites from './PageList/PageFavorites.vue'
import PageListItem from './PageList/PageListItem.vue'
import PageTrash from './PageList/PageTrash.vue'
import SubpageList from './PageList/SubpageList.vue'
import PageTag from './PageTag.vue'
import SkeletonLoading from './SkeletonLoading.vue'
import { useNetworkState } from '../composables/useNetworkState.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { usePagesStore } from '../stores/pages.js'
import { useRootStore } from '../stores/root.js'
import { useSearchStore } from '../stores/search.js'
import { useTagsStore } from '../stores/tags.js'
import { scrollToPage } from '../util/scrollToElement.js'
import { pageOrders } from '../util/sortOrders.js'

import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'

export default {
	name: 'PageList',

	components: {
		NcActionButton,
		NcActions,
		NcAppContentList,
		NcAppNavigationCaption,
		NcButton,
		NcPopover,
		NcTextField,
		NewPageDialog,
		SkeletonLoading,
		CloseIcon,
		DraggableElement,
		PageFavorites,
		PageListItem,
		PageTag,
		PageTrash,
		SubpageList,
		SortAlphabeticalAscendingIcon,
		SortAlphabeticalDescendingIcon,
		SortAscendingIcon,
		SortClockAscendingIcon,
		SortClockDescendingIcon,
		RecycleScroller,
	},

	setup() {
		const { networkOnline } = useNetworkState()
		const pageListFiltered = ref()
		const { height: pageListFilteredHeight } = useElementSize(pageListFiltered)
		return { networkOnline, pageListFiltered, pageListFilteredHeight }
	},

	data() {
		return {
			filterString: '',
			contentFilteredPages: [],
			loadingContentFilteredPages: false,
			getContentFilteredPagesDebounced: debounce(this.getContentFilteredPages, 700),
			showTagSelection: false,
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

		...mapState(useTagsStore, ['sortedTags', 'filterTags']),
		...mapState(usePagesStore, [
			'rootPage',
			'currentPage',
			'newPageParentId',
			'hasFavoritePages',
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
			return this.allPagesSortedCached
				// Filter by page title search string
				.filter((p) => p.title.toLowerCase().includes(this.filterString.toLowerCase()))
				// Filter by page tags
				.filter((p) => this.filterTags.every((t) => p.tags.includes(t.id)))
		},

		filterStringTagPart() {
			return this.filterString.toLowerCase().split(' ').pop()
		},

		filterStringTags() {
			if (!this.filterStringTagPart) {
				return []
			}
			return this.sortedTags
				// Ignore already selected tags
				.filter((t) => !this.filterTags.some((ft) => ft.id === t.id))
				.filter((t) => t.name.toLowerCase().includes(this.filterStringTagPart))
				.sort((t1, t2) => {
					if (t1.name.toLowerCase().startsWith(this.filterStringTagPart)) {
						return -1
					} else if (t2.name.toLowerCase().startsWith(this.filterStringTagPart)) {
						return 1
					} else if (t1.name.toLowerCase().split(' ').some((str) => str.startsWith(this.filterStringTagPart))) {
						return -1
					} else if (t2.name.toLowerCase().split(' ').some((str) => str.startsWith(this.filterStringTagPart))) {
						return 1
					}
					return 0
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

		sortedByString() {
			if (this.sortedBy('byTitleAsc')) {
				return t('collectives', 'Sorted ascending by title')
			} else if (this.sortedBy('byTitleDesc')) {
				return t('collectives', 'Sorted descending by title')
			} else if (this.sortedBy('byTimeAsc')) {
				return t('collectives', 'Sorted by recently changed')
			} else {
				return t('collectives', 'Sorted by least recently changed')
			}
		},

		showFavorites() {
			return !this.isFilteredView && this.hasFavoritePages
		},

		isFilteredView() {
			return this.filterString !== '' || this.filterTags.length > 0
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
		'currentCollective.id': function() {
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

		filterStringTags(val) {
			this.showTagSelection = val && val.length > 0
		},
	},

	methods: {
		...mapActions(useRootStore, ['show']),
		...mapActions(useTagsStore, ['addFilterTagId', 'removeFilterTagId']),
		...mapActions(useCollectivesStore, ['setCollectiveUserSettingPageOrder']),
		...mapActions(usePagesStore, ['contentSearch', 'setPageOrder']),
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
			try {
				const result = await this.contentSearch(this.filterString)
				this.contentFilteredPages = result.data?.ocs.data.pages || []
			} finally {
				// prevent showing old results
				if (oldFilterString === this.filterString) {
					this.loadingContentFilteredPages = false
				}
			}
		},

		onPageFilterTabKey(event) {
			const firstTagLink = this.$refs.filterStringTag[0]?.$refs?.link
			if (this.showTagSelection && firstTagLink) {
				event.preventDefault()
				firstTagLink.focus()
			}
		},

		onSelectFilterTag(tagId) {
			this.addFilterTagId(tagId)
			// Remove the tag search part from filter string when selecting the tag
			this.filterString = this.filterString.substring(0, this.filterString.length - this.filterStringTagPart.length)
			this.$refs.pageFilter.focus()
		},

		stopTagSelection() {
			this.showTagSelection = false
			this.$refs.pageFilter.focus()
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

.page-list-dragarea {
	padding-bottom: 20px;
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
		// Required for ellipsised text overflow
		max-width: calc(100% - 33px);
		padding: 7px;
		margin-left: 33px; // 40px - 7px
		background-color: var(--color-primary-element-light);
		border-radius: var(--border-radius-element, var(--border-radius-large));

		&-text {
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;
		}

		&-button {
			min-height: 20px;
			min-width: 20px;
			height: 20px;
			width: 20px !important;
			padding: 7px;
			margin-left: 10px;
		}
	}
}

.page-filter-tag-select {
	max-height: 200px;
	max-width: 140px;
	padding: var(--default-grid-baseline);
	overflow-y: auto;
}

.page-filter-tags {
	padding-bottom: var(--default-grid-baseline);
}

.page-tags {
	display: flex;
	flex-wrap: wrap;
	padding-inline: calc(2 * var(--default-grid-baseline));
	gap: var(--default-grid-baseline);

	&.select-popover {
		padding-inline: unset;
		flex-direction: column;
	}
}
</style>

<style lang="scss">
.page-filter-popover {
	margin-top: -18px;
	margin-left: -4px;

	.v-popper__arrow-container {
		display: none;
	}
}
</style>
