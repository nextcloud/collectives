<template>
	<AppContentList :class="{loading: loading('collective')}"
		:show-details="showing('details')">
		<PagesListItem v-if="currentCollective"
			key="Readme"
			:to="`/${encodeURIComponent(collectiveParam)}`"
			:title="currentCollective.name"
			@click.native="show('details')">
			<template v-if="currentCollective.emoji" #icon>
				{{ currentCollective.emoji }}
			</template>
			<template v-if="collectivePage" #line-two>
				<LastUpdate :timestamp="collectivePage.timestamp"
					:user="collectivePage.lastUserId" />
			</template>
			<template #actions>
				<ActionButton class="primary"
					icon="icon-add"
					@click="newPage">
					{{ t('collectives', 'Add a page') }}
				</ActionButton>
			</template>
		</PagesListItem>
		<PagesListItem v-for="page in pages"
			:key="page.title"
			:to="`/${encodeURIComponent(collectiveParam)}/${encodeURIComponent(page.title)}`"
			:page-id="page.id"
			:indent="1"
			:title="page.title"
			@click.native="show('details')">
			<template #line-two>
				<LastUpdate :timestamp="page.timestamp"
					:user="page.lastUserId" />
			</template>
		</PagesListItem>
	</AppContentList>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import LastUpdate from './LastUpdate'
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
	},

	computed: {
		...mapGetters([
			'collectiveParam',
			'collectivePage',
			'currentCollective',
			'loading',
			'showing',
			'mostRecentPages',
		]),
		pages() {
			return this.mostRecentPages
		},
	},

	methods: {
		...mapMutations(['show']),
		/**
		 * Create a new page and focus the page  automatically
		 */
		async newPage() {
			const page = {
				title: t('collectives', 'New Page'),
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
