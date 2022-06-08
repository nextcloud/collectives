<template>
	<EmptyContent>
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
	</EmptyContent>
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { mapGetters } from 'vuex'
import PageIcon from '../Icon/PageIcon'

export default {
	name: 'PageNotFound',

	components: {
		EmptyContent,
		PageIcon,
	},

	computed: {
		...mapGetters([
			'pageParam',
		]),
	},
}
</script>
