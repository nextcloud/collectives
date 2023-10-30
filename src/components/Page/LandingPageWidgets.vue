<template>
	<div class="landing-page-widgets"
		:class="[isFullWidthView ? 'full-width-view' : 'sheet-view']">
		<MembersWidget v-if="!isPublic" />
		<RecentPagesWidget />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import MembersWidget from './LandingPageWidgets/MembersWidget.vue'
import RecentPagesWidget from './LandingPageWidgets/RecentPagesWidget.vue'

export default {
	name: 'LandingPageWidgets',

	components: {
		MembersWidget,
		RecentPagesWidget,
	},

	computed: {
		...mapGetters([
			'isPublic',
			'isFullWidthView',
		]),
	},
}
</script>

<style scoped>
.landing-page-widgets {
	padding-left: 12px;
}

@media print {
	/* Don't print unwanted elements */
	.landing-page-widgets {
		display: none !important;
	}
}
</style>
