<template>
	<EmptyContent icon="icon-collectives">
		{{ t('collectives', 'Collective not found:') }}
		{{ collectiveParam }}
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

export default {
	name: 'CollectiveNotFound',

	components: {
		EmptyContent,
	},

	computed: {
		...mapGetters([
			'collectiveParam',
		]),
	},
}
</script>
