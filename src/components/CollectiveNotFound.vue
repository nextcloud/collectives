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
import { t } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
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

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.content-not-found {
	height: 100%;
}
</style>
