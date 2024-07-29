<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppContent :show-details="showing('details')"
		:list-size="20"
		:list-min-width="15"
		@update:showDetails="hide('details')">
		<template #list>
			<PageList v-if="currentCollective" />
		</template>
		<Collective v-if="currentCollective" />
		<NcEmptyContent v-else-if="loading('collectives')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>
		<CollectiveNotFound v-else />
	</NcAppContent>
</template>

<script>

import { mapActions, mapState } from 'pinia'
import { useRootStore } from '../stores/root.js'
import { useCollectivesStore } from '../stores/collectives.js'
import { NcAppContent, NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import Collective from '../components/Collective.vue'
import CollectiveNotFound from '../components/CollectiveNotFound.vue'
import PageList from '../components/PageList.vue'

export default {
	name: 'CollectiveView',

	components: {
		Collective,
		CollectiveNotFound,
		NcAppContent,
		NcEmptyContent,
		NcLoadingIcon,
		PageList,
	},

	computed: {
		...mapState(useRootStore, ['loading', 'showing']),
		...mapState(useCollectivesStore, ['currentCollective']),
	},

	methods: {
		...mapActions(useRootStore, ['hide']),
	},
}
</script>

<style lang="scss">
// Align details toggle button with page title bar (only relevant on mobile)
button.app-details-toggle {
	z-index: 10023 !important;
	top: 58px !important;
	position: fixed !important;
}
</style>
