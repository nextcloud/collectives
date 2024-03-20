<template>
	<div class="landing-page-widgets">
		<div class="first-row-widgets">
			<MembersWidget v-if="!isPublic" :current-collective="currentCollective" />
			<NcButton v-if="'contacts' in OC.appswebroots" :href="teamUrl">
				<template #icon>
					<TeamsIcon :size="20" />
				</template>
				<template #default>
					{{ t('collectives','Team') }}
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

export default {
	name: 'LandingPageWidgets',

	components: {
		MembersWidget,
		RecentPagesWidget,
		NcButton,
		TeamsIcon,
	},

	computed: {
		...mapGetters([
			'isPublic',
			'currentCollective',
		]),
		teamUrl() {
			return generateUrl('/apps/contacts/circle/{teamId}', { teamId: this.currentCollective.circleId })
		},
	},
}
</script>

<style scoped>
.landing-page-widgets {
	padding-left: 12px;
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
