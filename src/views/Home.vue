<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

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
import { mapState } from 'pinia'
import { useCollectivesStore } from '../stores/collectives.js'

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
		...mapState(useCollectivesStore, ['collectives', 'collectivePath']),
	},

	mounted() {
		if (this.collectives.length === 1) {
			// Open collective if only one exists
			this.$router.push(this.collectivePath(this.collectives[0]))
		} else if (this.collectives.length > 1) {
			// Open the navigation (on mobile) if we have collectives
			emit('toggle-navigation', { open: true })
		}
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
