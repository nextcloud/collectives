<template>
	<NcAppContentList :show-details="showing('details')">
		<div class="page-list-headerbar">
			<NcTextField name="pageFilter"
				:label="t('collectives', 'Search pages')"
				:value.sync="filterString"
				class="page-filter"
				:placeholder="t('collectives', 'Search pages ...')" />
			<NcActions class="toggle toggle-push-to-right">
				<NcActionButton class="toggle-button"
					:aria-label="labels.showTemplates"
					@click="toggleTemplates()">
					<template #icon>
						<PagesTemplateIcon :size="12" :fill-color="showTemplates ? 'currentColor' : 'var(--color-text-maxcontrast)'" />
					</template>
					{{ labels.showTemplates }}
				</NcActionButton>
			</NcActions>
			<NcActions class="toggle"
				:aria-label="t('collectives', 'Sort order')">
				<template #icon>
					<SortAscendingIcon v-if="sortedBy('byOrder')" :size="16" />
					<SortAlphabeticalAscendingIcon v-else-if="sortedBy('byTitle')" :size="16" />
					<SortClockAscendingOutlineIcon v-else :size="16" />
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
						<SortClockAscendingOutlineIcon :size="20" />
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
		<div v-if="!currentCollective || !rootPage || loading('collective')" class="page-list">
			<SkeletonLoading type="items" :count="3" />
		</div>
		<div v-else class="page-list">
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
				:has-template="hasTemplate"
				:filtered-view="false"
				class="page-list-root-page"
				@click.native="show('details')" />
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
			<SubpageList v-if="templateView"
				:key="templateView.id"
				:page="templateView"
				:level="1"
				:filtered-view="isFilteredview"
				:is-template="true" />
			<div v-if="isFilteredview">
				<NcAppNavigationCaption v-if="filteredPages.length > 0" :name="t('Collectives','Results in title')" />
				<RecycleScroller v-if="filteredPages.length > 0"
					v-slot="{ item }"
					class="scroller"
					:class="{ fullscroller: !loadingContentFilteredPages && contentFilteredPages.length <= 0 }"
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
					class="scroller contentFiltered"
					:class="{ fullscroller: filteredPages.length <= 0 }"
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
			<Draggable v-else
				:list="subpages"
				:parent-id="rootPage.id"
				:disable-sorting="isFilteredview">
				<SubpageList v-for="page in subpages"
					:key="page.id"
					:data-page-id="page.id"
					:page="page"
					:level="1"
					:filtered-view="false"
					class="page-list-drag-item" />
			</Draggable>
		</div>
		<PageTrash v-if="displayTrash" />
	</NcAppContentList>
</template>

<script>

import { mapActions, mapGetters, mapMutations } from 'vuex'
import { NcAppNavigationCaption, NcActionButton, NcActions, NcAppContentList, NcButton, NcTextField } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import Draggable from './PageList/Draggable.vue'
import SubpageList from './PageList/SubpageList.vue'
import Item from './PageList/Item.vue'
import PageTrash from './PageList/PageTrash.vue'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline.vue'
import PagesTemplateIcon from './Icon/PagesTemplateIcon.vue'
import { SET_COLLECTIVE_USER_SETTING_PAGE_ORDER } from '../store/actions.js'
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
		CloseIcon,
		Draggable,
		Item,
		PagesTemplateIcon,
		PageTrash,
		SubpageList,
		SortAlphabeticalAscendingIcon,
		SortAscendingIcon,
		SortClockAscendingOutlineIcon,
		RecycleScroller,
		NcAppNavigationCaption,
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
		...mapGetters([
			'currentCollectiveCanEdit',
			'rootPage',
			'templatePage',
			'currentCollective',
			'currentCollectiveIsPageShare',
			'currentCollectivePath',
			'currentPage',
			'isPublic',
			'keptSortable',
			'loading',
			'visibleSubpages',
			'sortBy',
			'showing',
			'showTemplates',
			'allPagesSorted',
		]),

		allPagesSortedCached() {
			return this.allPagesSorted(this.rootPage.id)
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

		hasTemplate() {
			return !!this.templatePage(this.rootPage ? this.rootPage.id : 0)
		},

		labels() {
			return {
				showTemplates: this.showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates'),
			}
		},

		templateView() {
			if (this.showTemplates && this.rootPage) {
				return this.templatePage(this.rootPage.id)
			} else {
				return null
			}
		},

		sortedBy() {
			return (sortOrder) => this.sortBy === sortOrder
		},

		isFilteredview() {
			return this.filterString !== ''
		},

		itemSize() {
			const defaultClickableArea = parseInt(window.getComputedStyle(document.body).getPropertyValue('--default-clickable-area'))
			return defaultClickableArea > 40
				? defaultClickableArea
				: defaultClickableArea + 4
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
		'currentPage.id'() {
			this.setSearchQuery(this.filterString)
		},
		filterString() {
			this.getContentFilteredPagesDebounced()
			this.setSearchQuery(this.filterString);
		},
	},

	methods: {
		...mapMutations([
			'setPageOrder',
			'show',
			'toggleTemplates',
			'setSearchQuery',
		]),

		...mapActions({
			dispatchSetUserPageOrder: SET_COLLECTIVE_USER_SETTING_PAGE_ORDER,
		}),

		/**
		 * Change page sort order and scroll to current page
		 *
		 * @param { string } order Sort order
		 */
		sortPagesAndScroll(order) {
			this.setPageOrder(order)
			if (!this.isPublic) {
				this.dispatchSetUserPageOrder({ id: this.currentCollective.id, pageOrder: pageOrders[order] })
					.catch((error) => {
						console.error(error)
						showError(t('collectives', 'Could not save page order for collective'))
					})
			}
			this.$nextTick(() => {
				scrollToPage(this.currentPage.id)
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
	// Overwrite this to fix stickyness of header and rootpage.
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

.action-item--single.toggle-push-to-right {
	margin-left: auto;
}

li.toggle-button.selected {
	background-color: var(--color-primary-element-light);
}

.page-list {
	flex-grow: 1;
	overflow: scroll;
	padding: 0 4px;
}

.page-list-root-page {
	position: sticky;
	top: 0;
	z-index: 1;
	background-color: var(--color-main-background);
	margin-block-end: 8px;
}

.sort-order-container {
	display: flex;
	align-items: center;

	position: sticky;
	// landing page + 8px margin-bottom
	top: calc(var(--landing-page-height));
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
