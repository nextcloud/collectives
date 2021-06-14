<template>
	<EmptyContent icon="icon-page">
		{{ t('collectives', 'Page not found:') }}
		{{ pageParam }}
		<template #desc>
			<div>
				{{ t('collectives', 'Could not find the Page.') }}
			</div>
			<div>
				{{ t('collectives', 'Select a page from the list or create a new one.') }}
			</div>
		</template>
	</EmptyContent>
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { mapGetters } from 'vuex'

export default {
	name: 'PageNotFound',

	components: {
		EmptyContent,
	},

	computed: {
		...mapGetters([
			'pageParam',
		]),
	},
}
</script>

<style>
.empty-content__icon.icon-page {
	background-size:44px;
}
</style>
