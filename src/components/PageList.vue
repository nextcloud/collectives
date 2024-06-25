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
					:item-size="44"
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
					:item-size="44"
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
		},
	},

	methods: {
		...mapMutations([
			'setPageOrder',
			'show',
			'toggleTemplates',
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

<style lang="scss" scoped>

.scroller {
	// NC header bar 50px; page list header bar 52px; landing page 48px; page trash 76px; NcAppNavigationCaption 78px divided by 2 for multiple scrollers
	max-height: calc((100vh - var(--header-height) - 52px - 48px - 76px - 78px * 2) / 2);
}

.fullscroller{
	// NC header bar 50px; page list header bar 52px; landing page 48px; page trash 76px; NcAppNavigationCaption 78px
	max-height: calc(100vh - var(--header-height) - 52px - 48px - 76px - 78px);
}

.app-content-list {
	// nextcloud-vue component sets `max-height: unset` on mobile.
	// Overwrite this to fix stickyness of header and rootpage.
	max-height: calc(100vh - 50px);
}

.page-list-headerbar {
	display: flex;
	flex-direction: row;
	position: sticky;
	top: 0;
	z-index: 2;
	background-color: var(--color-main-background);
	align-items: center;
	justify-content: space-between;
	margin-right: 4px;

	.page-filter {
		margin-left: 52px !important;
		padding-bottom: 2px;
	}
}

.toggle {
	height: 44px;
	width: 44px;
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
	padding: 0 4px;
}

.page-list-root-page {
	position: sticky;
	top: 52px;
	z-index: 1;
	background-color: var(--color-main-background);
}

.sort-order-container {
	display: flex;
	align-items: center;

	position: sticky;
	top: 100px; // 52px pagelist header + 44px landing page + 4px border-bottom
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
		border-radius: var(--border-radius-pill);

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
