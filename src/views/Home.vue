<template>
	<AppContent>
		<EmptyContent v-show="!isMobile" icon="icon-ant">
			{{ t('collectives', 'No collective selected') }}
			<template #desc>
				{{ t('collectives', 'Select a collective or create a new one on the left.') }}
			</template>
		</EmptyContent>
	</AppContent>
</template>

<script>

import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'

export default {
	name: 'Home',

	components: {
		AppContent,
		EmptyContent,
	},

	mixins: [
		isMobile,
	],

}
</script>
