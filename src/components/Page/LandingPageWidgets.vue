<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="landing-page-widgets"
		:class="[isFullWidth ? 'full-width-view' : 'sheet-view']">
		<MembersWidget v-if="!isPublic" />
		<RecentPagesWidget v-if="showRecentPages" />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import MembersWidget from './LandingPageWidgets/MembersWidget.vue'
import RecentPagesWidget from './LandingPageWidgets/RecentPagesWidget.vue'
import { usePagesStore } from '../../stores/pages.js'
import { useRootStore } from '../../stores/root.js'

export default {
	name: 'LandingPageWidgets',

	components: {
		MembersWidget,
		RecentPagesWidget,
	},

	props: {
		isFullWidth: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		...mapState(useRootStore, ['isPublic']),
		...mapState(usePagesStore, ['pages']),

		showRecentPages() {
			return this.pages.length > 3
		},
	},
}
</script>

<style scoped>
.landing-page-widgets {
	padding-inline: 14px 8px;
	padding-bottom: 12px;
	border-bottom: 1px solid var(--color-border);
}

@media print {
	/* Don't print unwanted elements */
	.landing-page-widgets {
		display: none !important;
	}
}
</style>
