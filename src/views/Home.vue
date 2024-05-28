<template>
	<NcAppContent>
		<NcEmptyContent :title="t('collectives', 'Collectives')"
			:description="t('collectives', 'Come, organize and build shared knowledge!')"
			class="content-home">
			<template #icon>
				<CollectivesIcon />
			</template>
			<template #action>
				<NcButton :aria-label="t('collectives', 'Create new collective')" :type="buttonType" @click="newCollective">
					{{ t('collectives', 'New collective') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</NcAppContent>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import { NcAppContent, NcEmptyContent, NcButton } from '@nextcloud/vue'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'
import { mapGetters } from 'vuex'

export default {
	name: 'Home',

	components: {
		NcAppContent,
		NcButton,
		CollectivesIcon,
		NcEmptyContent,
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
			if (oldval.length === 0 && val.length === 1) {
				this.$router.push(`/${encodeURIComponent(val[0].name)}`)
			} else if (oldval.length === 0 && val.length > 1) {
				emit('toggle-navigation', { open: true })
			}
		},
	},

	methods: {
		newCollective() {
			emit('toggle-navigation', { open: true })
			emit('open-new-collective-modal')
			this.buttonType = 'secondary'
		},
	},

}
</script>

<style lang="scss" scoped>
.content-home {
	height: 100%;
	padding: calc(var(--default-grid-baseline) * 4);
}
</style>
