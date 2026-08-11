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
import { t } from '@nextcloud/l10n'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import { useNetworkState } from '../composables/useNetworkState.js'

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

	methods: {
		t,

		newCollective() {
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
