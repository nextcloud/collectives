<template>
	<NcEmptyContent :title="t('collectives', 'Page not found: {page}', { page: pageParam })"
		:description="t('collectives', 'Select a page from the list or create a new one.')">
		<template #icon>
			<PageIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import { mapGetters } from 'vuex'
import { NcEmptyContent } from '@nextcloud/vue'
import PageIcon from '../Icon/PageIcon.vue'

export default {
	name: 'PageNotFound',

	components: {
		NcEmptyContent,
		PageIcon,
	},

	computed: {
		...mapGetters([
			'pageParam',
		]),
	},
}
</script>
