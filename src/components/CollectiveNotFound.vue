<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcEmptyContent
		:name="notFoundString"
		:description="t('collectives', 'You\'re not part of a collective with that name.')"
		class="content-not-found">
		<template #icon>
			<CollectivesIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import { mapState } from 'pinia'
import CollectivesIcon from './Icon/CollectivesIcon.vue'
import { useRootStore } from '../stores/root.js'

export default {
	name: 'CollectiveNotFound',

	components: {
		CollectivesIcon,
		NcEmptyContent,
	},

	computed: {
		...mapState(useRootStore, ['collectiveParam', 'collectiveId']),

		notFoundString() {
			return this.collectiveParam
				? t('collectives', 'Collective not found: {collective}', { collective: this.collectiveParam })
				: t('collectives', 'Collective with ID {id} not found', { id: this.collectiveId })
		},
	},
}
</script>

<style lang="scss" scoped>
.content-not-found {
	height: 100%;
}
</style>
