<template>
	<div class="landing-page-widgets">
		<MembersWidget v-if="!isPublic" />
	</div>
</template>

<script>
import { mapGetters } from 'vuex'
import MembersWidget from './LandingPageWidgets/MembersWidget.vue'

export default {
	name: 'LandingPageWidgets',

	components: {
		MembersWidget,
	},

	computed: {
		...mapGetters([
			'isPublic',
		]),
	},
}
</script>

<style lang="scss" scoped>
.landing-page-widgets {
	margin: auto;
	padding-left: 12px;
	max-width: 670px;
}
</style>
