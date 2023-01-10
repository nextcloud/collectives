<template>
	<NcEmptyContent :title="t('collectives', 'Collective not found: {collective}', { collective: collectiveParam })"
		:description="t('collectives', 'You\'re not part of a collective with that name.')">
		<template #icon>
			<CollectivesIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import { mapGetters } from 'vuex'
import CollectivesIcon from './Icon/CollectivesIcon.vue'

export default {
	name: 'CollectiveNotFound',

	components: {
		CollectivesIcon,
		NcEmptyContent,
	},

	computed: {
		...mapGetters([
			'collectiveParam',
		]),
	},
}
</script>
