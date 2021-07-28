<template>
	<AppContentList :class="{loading: loading('collective')}"
		:show-details="showing('details')">
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
		<Item v-if="currentCollective"
			key="Readme"
			:to="`/${encodeURIComponent(collectiveParam)}`"
			:title="currentCollective.name"
			:level="0"
			:collapsed="false"
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
			</template>
		</Item>
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
import { NEW_PAGE } from '../store/actions'

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
			'currentCollective',
			'loading',
			'visibleSubpages',
			'sortBy',
			'showing',
		]),
		subpages() {
			if (this.collectivePage) {
				return this.visibleSubpages(this.collectivePage.id)
			} else {
				return []
			}
		},
	},

	methods: {
		...mapMutations(['show', 'sortPages']),

		/**
		 * Create a new page and focus the page automatically
		 * @param {Object} parentPage Parent page
		 */
		async newPage(parentPage) {
			const page = {
				title: t('collectives', 'New Page'),
				filePath: '',
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
</style>
