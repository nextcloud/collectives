<template>
	<AppContentList :class="{loading: loading('collective')}"
		:show-details="showDetails">
		<PagesListItem v-if="currentCollective"
			key="Readme"
			:to="`/${encodeURIComponent(collectiveParam)}`"
			:title="currentCollective.name">
			<template #icon>
				{{ currentCollective.emoji }}
			</template>
			<template #line-two>
				<LastUpdate :timestamp="collectivePage.timestamp"
					:user="collectivePage.lastUserId" />
			</template>
			<template #actions>
				<ActionButton class="primary"
					icon="icon-add"
					@click="$emit('newPage')">
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
			@click.native="$emit('toggleDetails')">
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

import { mapGetters } from 'vuex'

export default {
	name: 'PagesList',

	components: {
		ActionButton,
		AppContentList,
		LastUpdate,
		PagesListItem,
	},

	props: {
		showDetails: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		...mapGetters([
			'collectiveParam',
			'collectivePage',
			'currentCollective',
			'loading',
			'mostRecentPages',
		]),
		pages() {
			return this.mostRecentPages
		},
	},
}

</script>

<style lang="scss" scoped>

	.app-content-list {
		padding-top: 40px;
	}

</style>
