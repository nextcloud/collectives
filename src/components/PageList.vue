<template>
	<NcAppContentList :class="{loading: loading('collective') || loading('pagelist')}"
		:show-details="showing('details')">
		<div class="page-list-headerbar">
			<NcTextField name="pageFilter"
				:value.sync="filterString"
				class="page-filter"
				:placeholder="t('collectives', 'Search pages ...')" />
			<NcActions class="toggle toggle-push-to-right">
				<NcActionButton class="toggle-button"
					:aria-label="labels.showTemplates"
					:title="labels.showTemplates"
					@click="toggleTemplates()">
					<template #icon>
						<PagesTemplateIcon :size="12" :fill-color="showTemplates ? 'currentColor' : 'var(--color-text-maxcontrast)'" />
					</template>
					{{ labels.showTemplates }}
				</NcActionButton>
			</NcActions>
			<NcActions class="toggle"
				:aria-label="t('collectives', 'Sort order')"
				:title="t('collectives', 'Sort order')">
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
		<div v-if="currentCollective && collectivePage" class="page-list">
			<Item key="Readme"
				:to="currentCollectivePath"
				:page-id="collectivePage.id"
				:parent-id="0"
				:title="currentCollective.name"
				:timestamp="collectivePage.timestamp"
				:last-user-id="collectivePage.lastUserId"
				:last-user-display-name="collectivePage.lastUserDisplayName"
				:emoji="currentCollective.emoji"
				:level="0"
				:can-edit="currentCollectiveCanEdit"
				:is-landing-page="true"
				:has-template="hasTemplate"
				:filtered-view="false"
				class="page-list-landing-page"
				@click.native="show('details')" />
			<Draggable v-if="subpages || keptSortable(currentPage.id)"
				:list="subpages"
				:parent-id="collectivePage.id"
				:disable-sorting="disableSorting">
				<template #header>
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
				</template>
				<SubpageList v-if="templateView"
					:key="templateView.id"
					:page="templateView"
					:level="1"
					:filter-string="filterString"
					:is-template="true" />
				<SubpageList v-for="page in subpages"
					:key="page.id"
					:data-page-id="page.id"
					:page="page"
					:level="1"
					:filter-string="filterString"
					class="page-list-drag-item" />
			</Draggable>
		</div>
	</NcAppContentList>
</template>

<script>

import { mapActions, mapGetters, mapMutations } from 'vuex'
import { NcActionButton, NcActions, NcAppContentList, NcButton, NcTextField } from '@nextcloud/vue'
import { showError } from '@nextcloud/dialogs'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import Draggable from './PageList/Draggable.vue'
import SubpageList from './PageList/SubpageList.vue'
import Item from './PageList/Item.vue'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending.vue'
import SortAscendingIcon from 'vue-material-design-icons/SortAscending.vue'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline.vue'
import PagesTemplateIcon from './Icon/PagesTemplateIcon.vue'
import { SET_COLLECTIVE_USER_SETTING_PAGE_ORDER } from '../store/actions.js'
import { scrollToPage } from '../util/scrollToElement.js'
import { pageOrders } from '../util/sortOrders.js'

export default {
	name: 'PageList',

	components: {
		NcActions,
		NcActionButton,
		NcAppContentList,
		NcButton,
		NcTextField,
		CloseIcon,
		Draggable,
		Item,
		PagesTemplateIcon,
		SubpageList,
		SortAlphabeticalAscendingIcon,
		SortAscendingIcon,
		SortClockAscendingOutlineIcon,
	},

	data() {
		return {
			filterString: '',
		}
	},

	computed: {
		...mapGetters([
			'currentCollectiveCanEdit',
			'collectivePage',
			'templatePage',
			'currentCollective',
			'currentCollectivePath',
			'currentPage',
			'isPublic',
			'keptSortable',
			'loading',
			'visibleSubpages',
			'sortBy',
			'showing',
			'showTemplates',
		]),

		subpages() {
			if (this.collectivePage) {
				return this.visibleSubpages(this.collectivePage.id)
			} else {
				return []
			}
		},

		hasTemplate() {
			return !!this.templatePage(this.collectivePage ? this.collectivePage.id : 0)
		},

		labels() {
			return {
				showTemplates: this.showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates'),
			}
		},

		templateView() {
			if (this.showTemplates && this.collectivePage) {
				return this.templatePage(this.collectivePage.id)
			} else {
				return null
			}
		},

		sortedBy() {
			return (sortOrder) => this.sortBy === sortOrder
		},

		disableSorting() {
			return this.filterString !== ''
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
	},
}

</script>

<style lang="scss" scoped>
.app-content-list {
	// nextcloud-vue component sets `max-height: unset` on mobile.
	// Overwrite this to fix stickyness of header and landingpage.
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
		margin-left: 50px !important;
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
	background-color: var(--color-primary-light);
}

.page-list {
	padding: 0 4px;
}

.page-list-landing-page {
	position: sticky;
	top: 44px;
	z-index: 1;
	background-color: var(--color-main-background);
}

.sort-order-container {
	display: flex;
	align-items: center;

	position: sticky;
	top: 92px; // 2x 44px + 4px border-bottom
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
		background-color: var(--color-primary-light);
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
