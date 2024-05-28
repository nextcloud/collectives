<template>
	<NcEmptyContent :name="t('collectives', 'Collective not found: {collective}', { collective: collectiveParam })"
		:description="t('collectives', 'You\'re not part of a collective with that name.')"
		class="content-not-found">
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

<style lang="scss" scoped>
.content-not-found {
	height: 100%;
	padding: calc(var(--default-grid-baseline) * 4);
}
</style>
