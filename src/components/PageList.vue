<template>
	<AppContentList :show-details="showing('details')">
		<div class="togglebar">
			<Actions class="toggle">
				<ActionButton
					class="sort"
					:aria-label="showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates')"
					:icon="showTemplates ? 'icon-pages-template-dark-grey' : 'icon-pages-template-grey'"
					:title="showTemplates ? t('collectives', 'Hide templates') : t('collectives', 'Show templates')"
					@click="toggleTemplates()" />
			</Actions>
			<Actions class="toggle"
				:aria-label="t('collectives', 'Sort order')"
				:default-icon="sortBy === 'byTitle' ? 'icon-sort-by-alpha' : 'icon-access-time'">
				<ActionButton
					class="sort"
					:class="{selected: sortBy === 'byTimestamp'}"
					icon="icon-access-time"
					:close-after-click="true"
					@click="sortPages('byTimestamp')">
					{{ t('collectives', 'Sort by last modification') }}
				</ActionButton>
				<ActionButton
					class="sort"
					:class="{selected: sortBy === 'byTitle'}"
					icon="icon-sort-by-alpha"
					:close-after-click="true"
					@click="sortPages('byTitle')">
					{{ t('collectives', 'Sort by title') }}
				</ActionButton>
			</Actions>
		</div>
		<Item v-if="currentCollective"
			key="Readme"
			:to="`/${encodeURIComponent(collectiveParam)}`"
			:title="currentCollective.name"
			:level="0"
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
			<template #actions>
				<ActionButton
					icon="icon-add"
					@click="newPage(collectivePage)">
					{{ t('collectives', 'Add a page') }}
				</ActionButton>
				<ActionButton v-if="showTemplates"
					icon="icon-pages-template-dark-grey"
					@click="editTemplate(collectivePage)">
					{{ editTemplateString }}
				</ActionButton>
			</template>
		</Item>
		<SubpageList v-if="templateView"
			:key="templateView.id"
			:page="templateView"
			:level="1"
			:is-template="true" />
		<SubpageList v-for="page in subpages"
			:key="page.id"
			:page="page"
			:level="1" />
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

export default {
	name: 'PageList',

	components: {
		Actions,
		ActionButton,
		AppContentList,
		LastUpdate,
		Item,
		SubpageList,
	},

	computed: {
		...mapGetters([
			'collectiveParam',
			'collectivePage',
			'templatePage',
			'currentCollective',
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
		 * Open existing or create new template page
		 * @param {Object} parentPage Parent page
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
		 * @param {Object} parentPage Parent page
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
.togglebar {
	display: flex;
	flex-direction: row;
	margin: 0 0 0 auto;
}

.toggle {
	height: 44px;
	width: 44px;
	padding: 0;
	margin: 0 0 0 auto;
}

.toggle:hover {
	opacity: 1;
}

li.sort.selected {
	background-color: var(--color-primary-light);
}

.emoji {
	margin: -3px
}

.icon-pages-template-dark-grey, .icon-pages-template-grey {
	background-size: 12px;
	height: revert;
}
</style>
