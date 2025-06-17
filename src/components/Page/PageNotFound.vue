<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcEmptyContent :name="t('collectives', 'Page not found: {page}', { page: pageParam })"
		:description="t('collectives', 'Select a page from the list or create a new one.')"
		class="content-not-found">
		<template #icon>
			<PageIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import { mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { NcEmptyContent } from '@nextcloud/vue'
import PageIcon from '../Icon/PageIcon.vue'

export default {
	name: 'PageNotFound',

	components: {
		NcEmptyContent,
		PageIcon,
	},

	computed: {
		...mapState(useRootStore, ['pageParam']),
	},
}
</script>

<style lang="scss" scoped>
.content-not-found {
	height: 100%;
}
</style>
