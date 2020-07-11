<template>
	<AppNavigation>
		<AppNavigationNew v-if="!loading"
			:text="t('wiki', 'New page')"
			:disabled="false"
			button-id="new-wiki-button"
			button-class="icon-add"
			@click="$emit('new')" />
		<ul>
			<AppNavigationItem v-for="page in pages"
				:key="page.id"
				:title="page.title ? page.title : t('wiki', 'New page')"
				:class="{active: currentPageId === page.id}"
				:to="`/${page.title}.md?fileId=${page.id}`" />
		</ul>
	</AppNavigation>
</template>

<script>
import AppNavigation from '@nextcloud/vue/dist/Components/AppNavigation'
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import AppNavigationNew from '@nextcloud/vue/dist/Components/AppNavigationNew'

export default {
	name: 'Nav',
	components: {
		AppNavigation,
		AppNavigationItem,
		AppNavigationNew,
	},
	props: {
		pages: {
			type: Array,
			required: true,
		},
		currentPageId: {
			type: Number,
			required: false,
			default: null,
		},
		loading: {
			type: Boolean,
			required: true,
		},
	},
}
</script>
