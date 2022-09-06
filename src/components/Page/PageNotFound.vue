<template>
	<NcEmptyContent>
		{{ t('collectives', 'Page not found:') }}
		{{ pageParam }}
		<template #icon>
			<PageIcon />
		</template>
		<template #desc>
			<div>
				{{ t('collectives', 'Could not find the Page.') }}
			</div>
			<div>
				{{ t('collectives', 'Select a page from the list or create a new one.') }}
			</div>
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
