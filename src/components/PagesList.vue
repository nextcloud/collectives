<template>
	<AppContentList :class="{loading: loading('collective')}"
		:show-details="showing('details')">
		<PagesListItem v-if="currentCollective"
			key="Readme"
			:to="`/${encodeURIComponent(collectiveParam)}`"
			:title="currentCollective.name"
			:level="0"
			:collapsed="false"
			@click.native="show('details')">
			<template v-if="currentCollective.emoji" #icon>
				<div>
					{{ currentCollective.emoji }}
				</div>
			</template>
			<template v-if="collectivePage" #line-two>
				<LastUpdate :timestamp="collectivePage.timestamp"
					:user="collectivePage.lastUserId" />
			</template>
			<template #actions>
				<ActionButton class="primary"
					icon="icon-add"
					@click="newPage(collectivePage)">
					{{ t('collectives', 'Add a page') }}
				</ActionButton>
			</template>
		</PagesListItem>
		<SubPagesList v-for="page in subpages"
			:key="page.id"
			:page="page"
			:level="1" />
	</AppContentList>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import LastUpdate from './LastUpdate'
import SubPagesList from './SubPagesList'
import PagesListItem from './PagesListItem'

import { showError } from '@nextcloud/dialogs'
import { mapGetters, mapMutations } from 'vuex'
import { NEW_PAGE } from '../store/actions'

export default {
	name: 'PagesList',

	components: {
		ActionButton,
		AppContentList,
		LastUpdate,
		PagesListItem,
		SubPagesList,
	},

	computed: {
		...mapGetters([
			'collectiveParam',
			'collectivePage',
			'currentCollective',
			'loading',
			'mostRecentSubpages',
			'showing',
		]),
		subpages() {
			if (this.collectivePage) {
				return this.mostRecentSubpages(this.collectivePage.id)
			} else {
				return []
			}
		},
	},

	methods: {
		...mapMutations(['show']),

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
				this.$router.push(this.$store.getters.updatedPagePath)
			} catch (e) {
				console.error(e)
				showError(t('collectives', 'Could not create the page'))
			}
		},
	},
}

</script>

<style lang="scss" scoped>

	.app-content-list {
		padding-top: 40px;
	}

</style>
