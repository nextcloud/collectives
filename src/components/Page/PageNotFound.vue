<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcEmptyContent
		:name="notFoundString"
		:description="t('collectives', 'Select a page from the list or create a new one.')"
		class="content-not-found">
		<template #icon>
			<PageIcon />
		</template>
	</NcEmptyContent>
</template>

<script>
import { NcEmptyContent } from '@nextcloud/vue'
import { mapState } from 'pinia'
import PageIcon from '../Icon/PageIcon.vue'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'PageNotFound',

	components: {
		NcEmptyContent,
		PageIcon,
	},

	computed: {
		...mapState(useRootStore, ['pageParam', 'pageId']),

		notFoundString() {
			return this.pageParam
				? t('collectives', 'Page not found: {page}', { page: this.pageParam })
				: t('collectives', 'Page with ID {id} not found', { id: this.pageId })
		},
	},
}
</script>

<style lang="scss" scoped>
.content-not-found {
	height: 100%;
}
</style>
