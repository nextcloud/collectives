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
					:icon="showTemplates ? 'icon-pages-template-dark-grey' : 'icon-pages-template-grey'"
					:title="showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates')"
					@click="toggleTemplates()" />
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
				@click.native="show('details')">
				<template v-if="currentCollective.emoji" #icon>
					<div class="emoji">
						{{ currentCollective.emoji }}
					</div>
				</template>
				<template v-if="collectivePage" #line-two>
					<LastUpdate :timestamp="collectivePage.timestamp"
						:user="collectivePage.lastUserId" />
				</template>
				<template v-if="!isCurrentCollectiveReadOnly" #actions>
					<ActionButton icon="icon-add"
						@click="newPage(collectivePage)">
						{{ t('collectives', 'Add a page') }}
					</ActionButton>
					<ActionButton v-if="showTemplates"
						icon="icon-pages-template-dark-grey"
						class="action-button__template"
						@click="editTemplate(collectivePage)">
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
import LastUpdate from './PageList/LastUpdate'
import SubpageList from './PageList/SubpageList'
import Item from './PageList/Item'
import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { NEW_PAGE, NEW_TEMPLATE } from '../store/actions'
import SortAlphabeticalAscendingIcon from 'vue-material-design-icons/SortAlphabeticalAscending'
import SortClockAscendingOutlineIcon from 'vue-material-design-icons/SortClockAscendingOutline'
import scrollToElement from '../util/scrollToElement'

export default {
	name: 'PageList',

	components: {
		Actions,
		ActionButton,
		AppContentList,
		LastUpdate,
		Item,
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
			'isCurrentCollectiveReadOnly',
			'collectivePage',
			'templatePage',
			'currentCollective',
			'currentCollectivePath',
			'currentPage',
			'loading',
			'pagePath',
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
		...mapMutations(['show', 'sortPages', 'toggleTemplates']),

		/**
		 * Change page sort order and scroll to current page
		 *
		 * @param { string } order Sort order
		 */
		sortPagesAndScroll(order) {
			this.sortPages(order)
			this.$nextTick(() => {
				scrollToElement(document.getElementById(`page-${this.currentPage.id}`))
			})
		},

		/**
		 * Open existing or create new template page
		 *
		 * @param {object} parentPage Parent page
		 */
		async editTemplate(parentPage) {
			if (this.templatePage(parentPage.id)) {
				this.$router.push(this.pagePath(this.templatePage(parentPage.id)))
				return
			}

			try {
				await this.$store.dispatch(NEW_TEMPLATE, parentPage)
				this.$router.push(this.$store.getters.newPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},

		/**
		 * Create a new page and focus the page automatically
		 *
		 * @param {object} parentPage Parent page
		 */
		async newPage(parentPage) {
			const page = {
				title: t('collectives', 'New Page'),
				parentId: parentPage.id,
			}
			try {
				await this.$store.dispatch(NEW_PAGE, page)
				this.$router.push(this.$store.getters.newPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},
	},
}

</script>

<style lang="scss" scoped>
.page-list-headerbar {
	display: flex;
	flex-direction: row;
}

.page-list {
	overflow: scroll;
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

.emoji {
	margin: -3px;
}

.icon-pages-template-dark-grey, .icon-pages-template-grey {
	background-size: 12px;
	height: revert;
}

// template icon appears too big with default size (16px)
.action-button__template::v-deep .icon-pages-template-dark-grey {
	background-size: 14px;
}
</style>
