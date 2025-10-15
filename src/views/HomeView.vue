<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<NcEmptyContent
			:title="t('collectives', 'Collectives')"
			:description="t('collectives', 'Come, organize and build shared knowledge!')"
			class="content-home">
			<template #icon>
				<CollectivesIcon />
			</template>
			<template #action>
				<NcButton
					:aria-label="t('collectives', 'Create new collective')"
					:variant="buttonVariant"
					:disabled="!networkOnline"
					@click="newCollective">
					{{ t('collectives', 'New collective') }}
				</NcButton>
			</template>
		</NcEmptyContent>
	</NcAppContent>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import { NcAppContent, NcButton, NcEmptyContent } from '@nextcloud/vue'
import { mapState } from 'pinia'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import { useNetworkState } from '../composables/useNetworkState.js'
import { useCollectivesStore } from '../stores/collectives.js'

export default {
	name: 'HomeView',

	components: {
		NcAppContent,
		NcButton,
		CollectivesIcon,
		NcEmptyContent,
	},

	setup() {
		const { networkOnline } = useNetworkState()
		return { networkOnline }
	},

	data() {
		return {
			buttonVariant: 'primary',
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
			this.buttonVariant = 'secondary'
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
