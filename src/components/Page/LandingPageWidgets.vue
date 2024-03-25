<template>
	<div class="landing-page-widgets">
		<div class="first-row-widgets">
			<MembersWidget v-if="!isPublic" />
			<NcButton v-if="hasContactsApp" :href="teamUrl">
				<template #icon>
					<TeamsIcon :size="20" />
				</template>
				<template v-if="!isMobile" #default>
					{{ t('collectives','Team Overview') }}
				</template>
			</NcButton>
		</div>
		<RecentPagesWidget />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
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

	computed: {
		...mapGetters([
			'isPublic',
			'currentCollective',
		]),
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
	padding-inline: 12px;
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
