<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="landing-page-widgets"
		:class="[isFullWidth ? 'full-width-view' : 'sheet-view']">
		<div v-if="!isPublic" class="first-row-widgets">
			<MembersWidget />
			<NcButton v-if="hasContactsApp" :href="teamUrl" target="_blank">
				<template #icon>
					<TeamsIcon :size="20" />
				</template>
				<template v-if="!isMobile" #default>
					{{ t('collectives','Team overview') }}
				</template>
			</NcButton>
		</div>
		<RecentPagesWidget />
	</div>
</template>

<script>
import { mapState } from 'pinia'
import { useRootStore } from '../../stores/root.js'
import { useCollectivesStore } from '../../stores/collectives.js'
import MembersWidget from './LandingPageWidgets/MembersWidget.vue'
import RecentPagesWidget from './LandingPageWidgets/RecentPagesWidget.vue'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import TeamsIcon from '../Icon/TeamsIcon.vue'
import { generateUrl } from '@nextcloud/router'
import isMobile from '@nextcloud/vue/dist/Mixins/isMobile.js'

export default {
	name: 'LandingPageWidgets',

	components: {
		MembersWidget,
		RecentPagesWidget,
		NcButton,
		TeamsIcon,
	},

	mixins: [
		isMobile,
	],

	props: {
		isFullWidth: {
			type: Boolean,
			required: true,
		},
	},

	computed: {
		...mapState(useRootStore, ['isPublic']),
		...mapState(useCollectivesStore, ['currentCollective']),

		teamUrl() {
			return generateUrl('/apps/contacts/circle/{teamId}', { teamId: this.currentCollective.circleId })
		},

		hasContactsApp() {
			return 'contacts' in this.OC.appswebroots
		},
	},
}
</script>

<style scoped>
.landing-page-widgets {
	padding-inline: 14px 8px;
	border-bottom: 1px solid var(--color-border);
}

.first-row-widgets{
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-top: 12px;
}

@media print {
	/* Don't print unwanted elements */
	.landing-page-widgets {
		display: none !important;
	}
}
</style>
