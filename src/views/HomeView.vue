<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent>
		<NcEmptyContent
			v-if="loading('collectives') === false && sortedCollectives.length === 0"
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
		<NcEmptyContent v-else>
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>
	</NcAppContent>
</template>

<script>

import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import CollectivesIcon from '../components/Icon/CollectivesIcon.vue'
import { useNetworkState } from '../composables/useNetworkState.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { useRootStore } from '../stores/root.js'

export default {
	name: 'HomeView',

	components: {
		NcAppContent,
		NcButton,
		CollectivesIcon,
		NcEmptyContent,
		NcLoadingIcon,
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
		...mapState(useRootStore, ['loading']),
		...mapState(useCollectivesStore, ['sortedCollectives', 'lastVisitedCollectiveId', 'collectivePath']),

		targetCollective() {
			if (this.loading('collectives') !== false || this.sortedCollectives.length === 0) {
				return null
			}
			return this.sortedCollectives.find((c) => c.id === this.lastVisitedCollectiveId)
				?? this.sortedCollectives[0]
		},
	},

	watch: {
		targetCollective: {
			immediate: true,
			handler(collective) {
				if (collective) {
					this.$router.replace(this.collectivePath(collective))
				}
			},
		},
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
