<template>
	<AppContent>
		<EmptyContent icon="icon-collectives">
			{{ t('collectives', 'Collectives') }}
			<template #desc>
				{{ t('collectives', 'Come, organize and build shared knowledge!') }}
			</template>
		</EmptyContent>
		<div class="new_collective">
			<button :class="{ primary }" @click="newCollective">
				{{ t('collectives', 'Create new collective') }}
			</button>
		</div>
	</AppContent>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { mapGetters } from 'vuex'

export default {
	name: 'Home',

	components: {
		AppContent,
		EmptyContent,
	},

	mixins: [
		isMobile,
	],

	data() {
		return {
			primary: true,
		}
	},

	computed: {
		...mapGetters([
			'collectives',
		]),
	},

	methods: {
		newCollective() {
			emit('toggle-navigation', { open: true })
			emit('start-new-collective')
			this.primary = false
		},
	},

}
</script>

<style scoped>
.new_collective {
	text-align: center;
	width: 100%;
	margin-top: 10px;
}
</style>
