<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationItem
		:key="collective.circleId"
		:name="collective.name"
		:to="collectivePath(collective)"
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
	</NcAppNavigationItem>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import { mapActions, mapState } from 'pinia'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import CollectivesIcon from '../Icon/CollectivesIcon.vue'
import { useCollectivesStore } from '../../stores/collectives.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'CollectiveListItem',

	components: {
		NcAppNavigationItem,
		CollectivesIcon,
	},

	props: {
		collective: {
			type: Object,
			required: true,
		},
	},

	setup() {
		const isMobile = useIsMobile()
		return { isMobile }
	},

	data() {
		return {
			collectiveSubmenu: null,
		}
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
				emit('toggle-navigation', { open: false })
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
