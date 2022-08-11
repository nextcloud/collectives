<template>
	<AppContentList :class="{loading: loading('collective')}"
		:show-details="showing('details')">
		<div class="page-list-headerbar">
			<input v-model="filterString"
				class="page-filter"
				:placeholder="t('collectives', 'Search pages ...')"
				type="text">
			<Actions class="toggle toggle-push-to-right">
				<ActionButton class="toggle-button"
					:aria-label="showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates')"
					:title="showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates')"
					@click="toggleTemplates()">
					<template #icon>
						<PagesTemplateIcon :size="12" :fill-color="showTemplates ? 'currentColor' : 'var(--color-text-maxcontrast)'" />
					</template>
				</ActionButton>
			</Actions>
			<Actions class="toggle"
				:aria-label="t('collectives', 'Sort order')">
				<template #icon>
					<SortAlphabeticalAscendingIcon v-if="sortedBy('byTitle')" :size="16" />
					<SortClockAscendingOutlineIcon v-else :size="16" />
				</template>
				<ActionButton class="toggle-button"
					:class="{selected: sortedBy('byTimestamp')}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byTimestamp')">
					<template #icon>
						<SortClockAscendingOutlineIcon :size="16" />
					</template>
					{{ t('collectives', 'Sort recently changed first') }}
				</ActionButton>
				<ActionButton class="toggle-button"
					:class="{selected: sortedBy('byTitle')}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byTitle')">
					<template #icon>
						<SortAlphabeticalAscendingIcon :size="16" />
					</template>
					{{ t('collectives', 'Sort by title') }}
				</ActionButton>
			</Actions>
		</div>
		<div class="page-list">
			<Item v-if="currentCollective"
				key="Readme"
				:to="currentCollectivePath"
				:page-id="collectivePage ? collectivePage.id : 0"
				:parent-id="0"
				:title="currentCollective.name"
				:timestamp="collectivePage ? collectivePage.timestamp : 0"
				:last-user-id="collectivePage ? collectivePage.lastUserId : ''"
				:emoji="currentCollective.emoji"
				:level="0"
				:can-edit="currentCollectiveCanEdit"
				:is-landing-page="true"
				:has-template="hasTemplate"
				:filtered-view="false"
				class="page-list-landing-page"
				@click.native="show('details')" />
			<SubpageList v-if="templateView"
				:key="templateView.id"
				:page="templateView"
				:level="1"
				:filter-string="filterString"
				:is-template="true" />
			<SubpageList v-for="page in subpages"
				:key="page.id"
				:page="page"
				:level="1"
				:filter-string="filterString" />
		</div>
	</AppContentList>
</template>

<script>

import { mapActions, mapGetters, mapMutations } from 'vuex'
import { SET_COLLECTIVE_USER_SETTING_PAGE_ORDER } from '../store/actions.js'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import SubpageList from './PageList/SubpageList.vue'
import Item from './PageList/Item.vue'
import PagesTemplateIcon from './Icon/PagesTemplateIcon.vue'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline'
import { showError } from '@nextcloud/dialogs'
import { scrollToPage } from '../util/scrollToElement.js'
import { pageOrders } from '../util/sortOrders.js'

export default {
	name: 'PageList',

	components: {
		Actions,
		ActionButton,
		AppContentList,
		Item,
		PagesTemplateIcon,
		SubpageList,
		SortAlphabeticalAscendingIcon,
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
	},

	methods: {
		...mapMutations([
			'show',
			'sortPages',
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
			this.sortPages(order)
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
}

.page-filter {
	margin-left: 44px;
	width: calc(100% - 44px);
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
</style>
