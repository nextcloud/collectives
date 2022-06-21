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
						<PagesTemplateIcon :size="12" :fill-color="showTemplates ? 'currentColor' : 'var(--color-text-lighter)'" />
					</template>
				</ActionButton>
			</Actions>
			<Actions class="toggle"
				:aria-label="t('collectives', 'Sort order')">
				<SortAlphabeticalAscendingIcon v-if="sortBy === 'byTitle'"
					slot="icon"
					:size="16"
					decorative />
				<SortClockAscendingOutlineIcon v-else
					slot="icon"
					:size="16"
					decorative />
				<ActionButton class="toggle-button"
					:class="{selected: sortBy === 'byTimestamp'}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byTimestamp')">
					{{ t('collectives', 'Sort recently changed first') }}
					<SortClockAscendingOutlineIcon slot="icon"
						:size="16"
						decorative />
				</ActionButton>
				<ActionButton class="toggle-button"
					:class="{selected: sortBy === 'byTitle'}"
					:close-after-click="true"
					@click="sortPagesAndScroll('byTitle')">
					{{ t('collectives', 'Sort by title') }}
					<SortAlphabeticalAscendingIcon slot="icon"
						:size="16"
						decorative />
				</ActionButton>
			</Actions>
		</div>
		<div class="page-list">
			<Item v-if="currentCollective"
				key="Readme"
				:to="currentCollectivePath"
				:title="currentCollective.name"
				:level="0"
				:filtered-view="false"
				:page-id="collectivePage ? collectivePage.id : 0"
				class="page-list-landing-page"
				@click.native="show('details')">
				<template v-if="currentCollective.emoji" #icon>
					<div class="landing-page-emoji">
						{{ currentCollective.emoji }}
					</div>
				</template>
				<template v-if="currentCollectiveCanEdit" #actions>
					<ActionButton icon="icon-add"
						:close-after-click="true"
						@click="newPage(collectivePage.id)">
						{{ t('collectives', 'Add a page') }}
					</ActionButton>
					<ActionButton v-if="showTemplates"
						class="action-button__template"
						:close-after-click="true"
						@click="editTemplate(collectivePage.id)">
						<template #icon>
							<PagesTemplateIcon :size="14" />
						</template>
						{{ editTemplateString }}
					</ActionButton>
				</template>
			</Item>
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

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import SubpageList from './PageList/SubpageList.vue'
import Item from './PageList/Item.vue'
import PagesTemplateIcon from './Icon/PagesTemplateIcon.vue'
import { mapActions, mapGetters, mapMutations } from 'vuex'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline'
import { showError } from '@nextcloud/dialogs'
import { scrollToPage } from '../util/scrollToElement.js'
import pageMixin from '../mixins/pageMixin.js'
import { SET_COLLECTIVE_USER_SETTING_PAGE_ORDER } from '../store/actions.js'
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

	mixins: [
		pageMixin,
	],

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

		templateView() {
			if (this.showTemplates && this.collectivePage) {
				return this.templatePage(this.collectivePage.id)
			} else {
				return null
			}
		},

		editTemplateString() {
			if (this.templateView) {
				return t('collectives', 'Edit template for subpages')
			} else {
				return t('collectives', 'Add template for subpages')
			}
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
			this.dispatchSetUserPageOrder({ id: this.currentCollective.id, pageOrder: pageOrders[order] })
				.catch((error) => {
					console.error(error)
					showError(t('collectives', 'Could not save page order for collective'))
				})
			this.$nextTick(() => {
				scrollToPage(this.currentPage.id)
			})
		},
	},
}

</script>

<style lang="scss" scoped>
.page-list-headerbar {
	display: flex;
	flex-direction: row;
}

.page-filter {
	margin-left: 48px;
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
	overflow-y: auto;
	height: 100%;
}

.page-list-landing-page {
	position: sticky;
	top: 0;
	z-index: 1;
	background-color: var(--color-main-background);
}

.landing-page-emoji {
	background-color: var(--color-main-background);
	margin: -3px;
}
</style>
