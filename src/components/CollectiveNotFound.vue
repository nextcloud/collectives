<template>
	<EmptyContent>
		{{ t('collectives', 'Collective not found:') }}
		{{ collectiveParam }}
		<template #icon>
			<CollectivesIcon />
		</template>
		<template #desc>
			<div>
				{{ t('collectives', 'You\'re not part of a collective with that name.') }}
			</div>
			<div>
				{{ t('collectives', 'Select a collective or create a new one on the left.') }}
			</div>
		</template>
	</EmptyContent>
</template>

<script>
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import { mapGetters } from 'vuex'
import CollectivesIcon from './Icon/CollectivesIcon.vue'

export default {
	name: 'CollectiveNotFound',

	components: {
		CollectivesIcon,
		EmptyContent,
	},

	computed: {
		...mapGetters([
			'collectiveParam',
		]),
	},
}
</script>
