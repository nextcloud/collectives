<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationItem
		:key="collective.circleId"
		:name="collective.name"
		:to="collectivePath(collective)"
		:force-menu="true"
		:force-display-actions="isMobile"
		class="collectives_list_item"
		@click="onClick">
		<template #icon>
			<template v-if="collective.emoji">
				{{ collective.emoji }}
			</template>
			<template v-else>
				<CollectivesIcon :size="20" />
			</template>
		</template>
		<template #actions>
			<CollectiveActions
				:collective="collective"
				:network-online="networkOnline" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import { NcAppNavigationItem } from '@nextcloud/vue'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import CollectiveActions from '../Collective/CollectiveActions.vue'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'CollectiveListItem',

	components: {
		NcAppNavigationItem,
		CollectiveActions,
		CollectivesIcon,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},

		networkOnline: {
			type: Boolean,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	computed: {
		...mapState(useCollectivesStore, ['collectivePath']),
	},

	methods: {
		...mapActions(useRootStore, ['show']),

		onClick() {
			if (this.isMobile) {
				// Go straight to landingpage on mobile. Also required to reload page list.
				this.show('details')
			}
		},
	},
}
</script>

<style lang="scss" scoped>
:deep(.app-navigation-entry-icon) {
	font-size: 20px;
}
</style>
