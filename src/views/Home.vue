<template>
	<AppContent>
		<EmptyContent icon="icon-collectives">
			{{ t('collectives', 'Collectives') }}
			<template #desc>
				{{ t('collectives', 'Come, organize and build shared knowledge!') }}
			</template>
		</EmptyContent>
		<div class="new_collective">
			<Button :aria-label="t('collectives', 'Create new collective')"
				:type="buttonType"
				@click="newCollective">
				{{ t('collectives', 'Create new collective') }}
			</Button>
		</div>
	</AppContent>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Button from '@nextcloud/vue/dist/Components/Button'
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile'
import { mapGetters } from 'vuex'

export default {
	name: 'Home',

	components: {
		AppContent,
		Button,
		EmptyContent,
	},

	mixins: [
		isMobile,
	],

	data() {
		return {
			buttonType: 'primary',
		}
	},

	computed: {
		...mapGetters([
			'collectives',
		]),
	},

	watch: {
		// Open the navigation if we already have collectives.
		// Only has an effect on mobile (where navigation is closed per default).
		'collectives'(val, oldval) {
			if (oldval.length === 0 && val.length > 0) {
				emit('toggle-navigation', { open: true })
			}
		},
	},

	methods: {
		newCollective() {
			emit('toggle-navigation', { open: true })
			emit('start-new-collective')
			this.buttonType = 'secondary'
		},
	},

}
</script>

<style scoped>
.new_collective {
	display: flex;
	justify-content: center;
	margin-top: 10px;
}
</style>
