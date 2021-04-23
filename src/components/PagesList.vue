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
			:timestamp="page.timestamp"
			:indent="1"
			:title="page.title"
			@click.native="$emit('toggleDetails')">
			<template #avatar>
				<Avatar v-if="page.lastUserId"
					:user="page.lastUserId"
					:disable-menu="true"
					:show-user-status="false"
					:tooltip-message="lastEditedUserMessage(page.lastUserId)"
					:size="20" />
			</template>
		</PagesListItem>
	</AppContentList>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import { mapGetters } from 'vuex'
import PagesListItem from './PagesListItem'

export default {
	name: 'PagesList',

	components: {
		ActionButton,
		AppContentList,
		PagesListItem,
		Avatar,
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
			'currentCollective',
			'loading',
			'mostRecentPages',
		]),
		pages() {
			return this.mostRecentPages
		},
	},

	methods: {
		lastEditedUserMessage(user) {
			return t('collectives', 'Last edited by {user}', { user })
		},
	},

}

</script>

<style lang="scss" scoped>

	.app-content-list {
		padding-top: 40px;
	}

</style>
