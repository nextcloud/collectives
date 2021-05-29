<template>
	<EmptyContent v-show="!isMobile" icon="icon-ant">
		{{ t('collectives', 'No collective selected') }}
		<template #desc>
			{{ t('collectives', 'Select a collective or create a new one on the left.') }}
		</template>
	</EmptyContent>
</template>

<script>

import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'

export default {
	name: 'Home',

	components: {
		EmptyContent,
	},

	mixins: [
		isMobile,
	],

}
</script>
