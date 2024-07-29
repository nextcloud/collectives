<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="shares-container">
		<!-- loading -->
		<NcEmptyContent v-if="loading('shares')">
			<template #icon>
				<NcLoadingIcon />
			</template>
		</NcEmptyContent>

		<!-- shares list -->
		<ul v-else class="sharing-list">
			<SharingEntryLink v-if="!shares.length" />
			<SharingEntryLink v-for="(share, index) in shares"
				v-else
				:key="share.id"
				:index="index + 1"
				:share="share" />
		</ul>
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useSharesStore } from '../../stores/shares.js'
import { usePagesStore } from '../../stores/pages.js'
import { NcEmptyContent, NcLoadingIcon } from '@nextcloud/vue'
import SharingEntryLink from './SharingEntryLink.vue'

export default {
	name: 'SidebarTabSharing',

	components: {
		NcEmptyContent,
		NcLoadingIcon,
		SharingEntryLink,
	},

	props: {
		pageId: {
			type: Number,
			required: true,
		},
	},

	computed: {
		...mapState(useRootStore, ['loading']),
		...mapState(useSharesStore, ['sharesByPageId']),
		...mapState(usePagesStore, ['isLandingPage']),

		shares() {
			return this.isLandingPage
				? this.sharesByPageId(0)
				: this.sharesByPageId(this.pageId)
		},
	},
}
</script>
