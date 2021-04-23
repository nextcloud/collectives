<template>
	<AppContentList :class="{loading: $store.state.loading.collective}"
		:show-details="showDetails">
		<div class="app-content-list-item">
			<button class="primary"
				:disabled="$store.state.loading.page"
				@click="$emit('newPage')">
				<span class="icon icon-add-white" />
				{{ t('collectives', 'Add a page') }}
			</button>
		</div>
		<PagesListItem v-for="page in pages"
			:key="page.title"
			:page="page"
			@click.native="$emit('toggleDetails')" />
	</AppContentList>
</template>

<script>

import AppContentList from '@nextcloud/vue/dist/Components/AppContentList'
import PagesListItem from './PagesListItem'

export default {
	name: 'PagesList',

	components: {
		AppContentList,
		PagesListItem,
	},

	props: {
		showDetails: {
			type: Boolean,
			default: true,
		},
	},

	computed: {
		pages() {
			return this.$store.getters.mostRecentPages
		},
	},
}

</script>

<style lang="scss" scoped>

	.app-content-list {
		padding-top: 40px;
	}

</style>
