<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent
		:showDetails="showing('details')"
		:listSize="20"
		:listMinWidth="15"
		@update:showDetails="hide('details')">
		<template #list>
			<NcAppContentList :showDetails="showing('details')">
				<CollectiveSelector />
			</NcAppContentList>
		</template>
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
import { mapActions, mapState } from 'pinia'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppContentList from '@nextcloud/vue/components/NcAppContentList'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import CollectiveSelector from '../components/Nav/CollectiveSelector.vue'
import { useNetworkState } from '../composables/useNetworkState.js'
import { useRootStore } from '../stores/root.js'

export default {
	name: 'HomeView',

	components: {
		CollectiveSelector,
		NcAppContent,
		NcAppContentList,
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
		...mapState(useRootStore, ['showing']),
	},

	methods: {
		t,

		...mapActions(useRootStore, ['hide']),

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
