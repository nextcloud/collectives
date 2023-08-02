<template>
	<div class="landing-page-widgets">
		<RecentPagesWidget />
		<MembersWidget v-if="!isPublic" />
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
		]),
	},
}
</script>

<style scoped>
.landing-page-widgets {
	margin: auto;
	padding-left: 12px;
	max-width: 670px;
}
</style>
